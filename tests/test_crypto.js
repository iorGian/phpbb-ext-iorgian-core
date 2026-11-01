// Usage: node tests/test_crypto.js /path/to/sodium.js   (needs Node >= 18; no phpBB required)
const fs = require('fs');
const vm = require('vm');
// Browser-like context: window/self, WebCrypto, no `process`, no `require`.
const sandbox = { console, __enc: (t) => Array.from(Buffer.from(t, 'utf8')), __dec: (a) => Buffer.from(a).toString('utf8'), setTimeout, clearTimeout, crypto: require('crypto').webcrypto, URLSearchParams, fetch: undefined };
sandbox.window = sandbox; sandbox.self = sandbox;
vm.createContext(sandbox);
// TextEncoder/TextDecoder created INSIDE the context, so typed arrays belong to the same realm (as in a real browser)
vm.runInContext('globalThis.TextEncoder = class { encode(s) { return new Uint8Array(__enc(s)); } }; globalThis.TextDecoder = class { decode(u) { return __dec(Array.from(u || [])); } };', sandbox);
vm.runInContext(fs.readFileSync(process.argv[2] || 'sodium.js', 'utf8'), sandbox, { filename: 'sodium.js' });
vm.runInContext(fs.readFileSync(require('path').join(__dirname, '../styles/all/template/js/iorgian_crypto.js'), 'utf8'), sandbox, { filename: 'iorgian_crypto.js' });
const IorgianCrypto = sandbox.IorgianCrypto;
const window = sandbox;

let pass = 0, fail = 0;
function ok(name, cond) { if (cond) { pass++; console.log('  ok   ' + name); } else { fail++; console.log('  FAIL ' + name); } }
function throws(name, fn, msg) {
  try { fn(); fail++; console.log('  FAIL ' + name + ' (no error)'); }
  catch (e) { const good = !msg || String(e.message).includes(msg); good ? (pass++, console.log('  ok   ' + name + ' -> ' + e.message.slice(0, 40))) : (fail++, console.log('  FAIL ' + name + ' got: ' + e.message)); }
}

(async () => {
  const C = await IorgianCrypto.ready();
  const S = window.sodium;
  console.log('libsodium', S.sodium_version_string ? S.sodium_version_string() : '?', '| pad:', typeof S.pad, '| pwhash:', typeof S.crypto_pwhash);

  const fast = { ops: 2, memKiB: 32768 }; // the PHP minimums
  console.log('\n[identities]');
  const alice = C.createIdentity('alice-pass', fast);
  const bob = C.createIdentity('bob-pass', fast);
  const carol = C.createIdentity('carol-pass', fast);
  const dflt = C.createIdentity('x');  // default KDF
  ok('default KDF is ops=3, 64 MiB', dflt.bundle.kdf_ops === 3 && dflt.bundle.kdf_mem_kib === 65536);

  // sizes the PHP validator assumes
  const raw = (b) => Buffer.from(b, 'base64');
  const canon = (b) => raw(b).toString('base64') === b;
  ok('public_key = 32 bytes, canonical b64', raw(alice.bundle.public_key).length === 32 && canon(alice.bundle.public_key));
  ok('wrapped_key = 72 bytes, canonical b64', raw(alice.bundle.wrapped_key).length === 72 && canon(alice.bundle.wrapped_key));
  ok('kdf_salt = 16 bytes, canonical b64', raw(alice.bundle.kdf_salt).length === 16 && canon(alice.bundle.kdf_salt));

  console.log('\n[unlock]');
  const skA = C.unlock(alice.bundle, 'alice-pass');
  ok('correct passphrase unlocks', Buffer.from(skA).equals(Buffer.from(alice.secretKey)));
  throws('wrong passphrase', () => C.unlock(alice.bundle, 'nope'), 'WRONG_PASSPHRASE');
  throws('swapped public key', () => C.unlock(Object.assign({}, alice.bundle, { public_key: bob.bundle.public_key }), 'alice-pass'), 'KEY_MISMATCH');
  const w = raw(alice.bundle.wrapped_key); w[40] ^= 1;
  throws('tampered wrapped key', () => C.unlock(Object.assign({}, alice.bundle, { wrapped_key: w.toString('base64') }), 'alice-pass'), 'WRONG_PASSPHRASE');

  console.log('\n[change passphrase]');
  const nb = C.changePassphrase(skA, alice.bundle.public_key, 'new-pass', fast);
  ok('same public key kept', nb.public_key === alice.bundle.public_key);
  ok('new salt and blob', nb.kdf_salt !== alice.bundle.kdf_salt && nb.wrapped_key !== alice.bundle.wrapped_key);
  ok('new passphrase works', Buffer.from(C.unlock(nb, 'new-pass')).equals(Buffer.from(skA)));
  throws('old passphrase rejected', () => C.unlock(nb, 'alice-pass'), 'WRONG_PASSPHRASE');

  console.log('\n[encrypt / decrypt]');
  const payload = { subject: 'Hola ñ ș ț 🔒', body: 'Mensaje secreto\ncon varias líneas' };
  const armored = C.encrypt(payload, 1, alice.secretKey, [alice.bundle.public_key, bob.bundle.public_key]);
  ok('armored is plain base64 ASCII', /^[A-Za-z0-9+/=]+$/.test(armored));
  const bobGot = C.decrypt(armored, bob.secretKey, alice.bundle.public_key);
  ok('bob reads payload', JSON.stringify(bobGot.data) === JSON.stringify(payload) && bobGot.sender === 1);
  const aliceGot = C.decrypt(armored, alice.secretKey, alice.bundle.public_key);
  ok('sender reads own copy', JSON.stringify(aliceGot.data) === JSON.stringify(payload));
  throws('carol (not a recipient)', () => C.decrypt(armored, carol.secretKey, alice.bundle.public_key), 'NOT_A_RECIPIENT');
  throws('wrong sender public key', () => C.decrypt(armored, bob.secretKey, carol.bundle.public_key));
  ok('plaintext not visible in armored text', !Buffer.from(armored, 'base64').toString().includes('secreto') && !armored.includes('Mensaje'));

  console.log('\n[envelope shape, ported 1:1 from service/envelope.php]');
  const phpParse = (armored) => {
    if (!armored || armored.length > 1048576) return null;
    if (!/^[A-Za-z0-9+/]*={0,2}$/.test(armored)) return null;
    const raw = Buffer.from(armored, 'base64');
    if (raw.length === 0 || raw.toString('base64') !== armored) return null;
    if (raw.length < 30 + 80 + 17 || raw[0] !== 1) return null;
    const sender = raw.readUInt32BE(1), count = raw[5];
    if (count < 1 || count > 255 || raw.length < 30 + count * 80 + 17) return null;
    const ids = new Set();
    for (let i = 0; i < count; i++) ids.add(raw.subarray(30 + i * 80, 38 + i * 80).toString('hex'));
    if (ids.size !== count) return null;
    return { sender, recipients: count, key_ids: [...ids] };
  };
  const rawEnv = raw(armored);
  const parsed = phpParse(armored);
  ok('validator accepts a real envelope', parsed !== null);
  ok('sender = 1, recipients = 2', parsed && parsed.sender === 1 && parsed.recipients === 2);
  ok('key ids match keyId()', parsed && parsed.key_ids.includes(C.keyId(alice.bundle.public_key)) && parsed.key_ids.includes(C.keyId(bob.bundle.public_key)));
  ok('version byte is 1', rawEnv[0] === 1);
  ok('padding: ciphertext is a multiple of 256 + 16', (rawEnv.length - 30 - 2 * 80 - 16) % 256 === 0);
  ok('validator rejects truncated data', phpParse(rawEnv.subarray(0, 60).toString('base64')) === null);
  ok('validator rejects wrong version', phpParse(Buffer.concat([Buffer.from([2]), rawEnv.subarray(1)]).toString('base64')) === null);
  ok('validator rejects non-canonical base64', phpParse(armored + '\n') === null && phpParse(armored.slice(0, -1)) === null);
  const dup = Buffer.from(rawEnv); rawEnv.copy(dup, 30 + 80, 30, 38);
  ok('validator rejects duplicated key id', phpParse(dup.toString('base64')) === null);
  const zero = Buffer.from(rawEnv); zero[5] = 0;
  ok('validator rejects zero recipients', phpParse(zero.toString('base64')) === null);
  console.log('       armored size, short message, 2 recipients:', armored.length, 'chars');

  console.log('\n[recipient order is shuffled]');
  const orders = new Set();
  for (let i = 0; i < 12; i++) {
    const a = C.encrypt('x', 1, alice.secretKey, [alice.bundle.public_key, bob.bundle.public_key, carol.bundle.public_key]);
    orders.add(phpParse(a).key_ids.join());
  }
  ok('more than one order seen in 12 runs (' + orders.size + ')', orders.size > 1);

  console.log('\n[tampering]');
  const tamper = (fn) => { const b = Buffer.from(rawEnv); fn(b); return b.toString('base64'); };
  const entryOf = (b, pub) => { const id = C.keyId(pub); for (let i = 0; i < b[5]; i++) { if (b.subarray(30 + i * 80, 38 + i * 80).toString('hex') === id) return 30 + i * 80; } };
  throws('flipped bit in ciphertext', () => C.decrypt(tamper(b => { b[b.length - 5] ^= 1; }), bob.secretKey, alice.bundle.public_key));
  throws('flipped bit in boxed key', () => C.decrypt(tamper(b => { b[entryOf(b, bob.bundle.public_key) + 40] ^= 1; }), bob.secretKey, alice.bundle.public_key));
  throws('flipped bit in content nonce', () => C.decrypt(tamper(b => { b[10] ^= 1; }), bob.secretKey, alice.bundle.public_key));
  throws('forged sender id', () => C.decrypt(tamper(b => { b.writeUInt32BE(2, 1); }), bob.secretKey, alice.bundle.public_key), 'SENDER_MISMATCH');
  throws('unsupported version', () => C.decrypt(tamper(b => { b[0] = 2; }), bob.secretKey, alice.bundle.public_key), 'UNSUPPORTED_VERSION');
  throws('truncated envelope', () => C.decrypt(rawEnv.subarray(0, 50).toString('base64'), bob.secretKey, alice.bundle.public_key), 'MALFORMED');
  throws('zero recipients', () => C.decrypt(tamper(b => { b[5] = 0; }), bob.secretKey, alice.bundle.public_key), 'MALFORMED');
  throws('duplicate recipient key', () => C.encrypt('x', 1, alice.secretKey, [bob.bundle.public_key, bob.bundle.public_key]), 'DUPLICATE_RECIPIENT_KEY');
  throws('bad sender id', () => C.encrypt('x', -1, alice.secretKey, [alice.bundle.public_key]), 'BAD_SENDER_ID');
  throws('no recipients', () => C.encrypt('x', 1, alice.secretKey, []), 'BAD_RECIPIENT_COUNT');
  const big32 = C.encrypt('x', 4294967295, alice.secretKey, [alice.bundle.public_key]);
  ok('sender id 2^32-1 roundtrips', C.decrypt(big32, alice.secretKey, alice.bundle.public_key).sender === 4294967295 && phpParse(big32).sender === 4294967295);

  console.log('\n[misc]');
  ok('senderOf()', C.senderOf(armored) === 1);
  const fp = C.fingerprint(alice.bundle.public_key);
  ok('fingerprint = 8 groups of 4 hex (' + fp + ')', /^([0-9a-f]{4} ){7}[0-9a-f]{4}$/.test(fp));
  const big = 'a'.repeat(40000);
  const bigArm = C.encrypt(big, 1, alice.secretKey, [alice.bundle.public_key, bob.bundle.public_key]);
  ok('40k-char message roundtrip', C.decrypt(bigArm, bob.secretKey, alice.bundle.public_key).data === big);
  console.log('       armored size for 40000 chars, 2 recipients:', bigArm.length);
  const many = Array.from({ length: 50 }, () => C.createIdentity('p', fast).bundle.public_key);
  const manyArm = C.encrypt('x', 1, alice.secretKey, many);
  console.log('       armored size for tiny message, 50 recipients:', manyArm.length);

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('CRASH', e); process.exit(2); });
