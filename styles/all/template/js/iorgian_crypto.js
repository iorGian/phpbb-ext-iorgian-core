/**
 * Iorgian Core - browser crypto module (zero-knowledge, end-to-end).
 *
 * Requires libsodium-wrappers (global `sodium`) loaded BEFORE this file.
 * All secrets stay in the browser: the server only ever receives a public key and
 * a private-key blob wrapped with a key derived (Argon2id) from the user's passphrase.
 *
 * Primitives: Argon2id (crypto_pwhash), XSalsa20-Poly1305 (crypto_secretbox),
 * X25519 + XSalsa20-Poly1305 (crypto_box, authenticated: recipients know who wrote it).
 *
 * @license GPL-2.0-only
 */
(function (global) {
	'use strict';

	var FORMAT_VERSION = 1;
	var PAD_BLOCK = 256;
	var MAX_RECIPIENTS = 255;
	var HEADER = 1 + 4 + 1 + 24; // version, sender id, recipient count, content nonce
	var ENTRY = 8 + 24 + 48;     // key id, nonce, boxed content key
	var MIN_CT = 17;             // one byte plus the 16-byte MAC
	var DEFAULT_KDF = { ops: 3, memKiB: 65536 }; // Argon2id, 64 MiB; stored with the bundle, can be raised later
	var S = null;
	var cfg = { endpoint: '' };

	function need(fn) {
		if (typeof S[fn] !== 'function') {
			throw new Error('IORGIAN_CRYPTO_MISSING_' + fn + ' (use libsodium-wrappers-sumo if the standard build lacks it)');
		}
	}

	function b64(u8) { return S.to_base64(u8, S.base64_variants.ORIGINAL); }
	function unb64(s) { return S.from_base64(s, S.base64_variants.ORIGINAL); }

	function deriveWrapKey(passphrase, salt, ops, memKiB) {
		return S.crypto_pwhash(
			S.crypto_secretbox_KEYBYTES,
			passphrase,
			salt,
			ops,
			memKiB * 1024,
			S.crypto_pwhash_ALG_ARGON2ID13
		);
	}

	function wrapSecret(secretKey, passphrase, kdf) {
		var salt = S.randombytes_buf(S.crypto_pwhash_SALTBYTES);
		var wrapKey = deriveWrapKey(passphrase, salt, kdf.ops, kdf.memKiB);
		var nonce = S.randombytes_buf(S.crypto_secretbox_NONCEBYTES);
		var box = S.crypto_secretbox_easy(secretKey, nonce, wrapKey);
		var blob = new Uint8Array(nonce.length + box.length);
		blob.set(nonce, 0);
		blob.set(box, nonce.length);
		S.memzero(wrapKey);

		return {
			wrapped_key: b64(blob),
			kdf_salt: b64(salt),
			kdf_ops: kdf.ops,
			kdf_mem_kib: kdf.memKiB
		};
	}

	/** 8-byte BLAKE2b of the public key, hex: identifies a recipient inside an envelope without exposing a user id. */
	function keyId(publicKeyB64) { return S.to_hex(S.crypto_generichash(8, unb64(publicKeyB64))); }

	function u32be(n) { return new Uint8Array([(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255]); }

	function sameBytes(a, b) {
		if (a.length !== b.length) { return false; }
		var d = 0;
		for (var i = 0; i < a.length; i++) { d |= a[i] ^ b[i]; }
		return d === 0;
	}

	function parseHeader(bytes) {
		if (bytes.length < 1) { throw new Error('MALFORMED'); }
		if (bytes[0] !== FORMAT_VERSION) { throw new Error('UNSUPPORTED_VERSION'); }
		if (bytes.length < HEADER + ENTRY + MIN_CT) { throw new Error('MALFORMED'); }
		var count = bytes[5];
		var ctOffset = HEADER + ENTRY * count;
		if (count < 1 || bytes.length < ctOffset + MIN_CT) { throw new Error('MALFORMED'); }
		return {
			sender: ((bytes[1] << 24) | (bytes[2] << 16) | (bytes[3] << 8) | bytes[4]) >>> 0,
			count: count,
			nonce: bytes.slice(6, 30),
			ctOffset: ctOffset
		};
	}

	function shuffle(arr) {
		for (var i = arr.length - 1; i > 0; i--) {
			var j = S.randombytes_uniform(i + 1);
			var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
		}
	}

	function pad(bytes) { return (typeof S.pad === 'function') ? S.pad(bytes, PAD_BLOCK) : bytes; }
	function unpad(bytes) { return (typeof S.unpad === 'function') ? S.unpad(bytes, PAD_BLOCK) : bytes; }

	var api = {
		/** Must be called once; resolves when libsodium is ready. */
		ready: function (options) {
			if (options && options.endpoint) { cfg.endpoint = options.endpoint.replace(/\/+$/, ''); }
			if (!global.sodium) { return Promise.reject(new Error('IORGIAN_CRYPTO_NO_SODIUM')); }
			return global.sodium.ready.then(function () {
				S = global.sodium;
				['crypto_pwhash', 'crypto_box_keypair', 'crypto_box_easy', 'crypto_box_open_easy',
					'crypto_secretbox_easy', 'crypto_secretbox_open_easy', 'crypto_scalarmult_base',
					'crypto_generichash', 'randombytes_buf', 'randombytes_uniform', 'from_hex', 'to_hex', 'memzero', 'to_base64', 'from_base64'].forEach(need);
				return api;
			});
		},

		/** New identity: keypair + wrapped private key. Returns {bundle, secretKey}. */
		createIdentity: function (passphrase, kdf) {
			kdf = kdf || DEFAULT_KDF;
			var kp = S.crypto_box_keypair();
			var wrapped = wrapSecret(kp.privateKey, passphrase, kdf);
			wrapped.public_key = b64(kp.publicKey);
			return { bundle: wrapped, secretKey: kp.privateKey };
		},

		/** Opens the bundle with the passphrase. Throws Error('WRONG_PASSPHRASE') on failure. */
		unlock: function (bundle, passphrase) {
			var blob = unb64(bundle.wrapped_key);
			var nonce = blob.slice(0, S.crypto_secretbox_NONCEBYTES);
			var box = blob.slice(S.crypto_secretbox_NONCEBYTES);
			var wrapKey = deriveWrapKey(passphrase, unb64(bundle.kdf_salt), bundle.kdf_ops, bundle.kdf_mem_kib);
			var sk;
			try {
				sk = S.crypto_secretbox_open_easy(box, nonce, wrapKey);
			} catch (e) {
				throw new Error('WRONG_PASSPHRASE');
			} finally {
				S.memzero(wrapKey);
			}
			// The unwrapped key must really belong to the published public key.
			if (b64(S.crypto_scalarmult_base(sk)) !== bundle.public_key) {
				S.memzero(sk);
				throw new Error('KEY_MISMATCH');
			}
			return sk;
		},

		/** Change passphrase: same identity, new wrapping. Returns bundle fields to POST. */
		changePassphrase: function (secretKey, publicKeyB64, newPassphrase, kdf) {
			var w = wrapSecret(secretKey, newPassphrase, kdf || DEFAULT_KDF);
			w.public_key = publicKeyB64;
			return w;
		},

		/** Wipe a secret from memory (call on lock / logout). */
		lock: function (secretKey) { if (secretKey) { S.memzero(secretKey); } },

		/**
		 * Encrypt any JSON-serialisable payload for several recipients (the sender's own public key must be
		 * included in `recipientPublicKeys` to be able to read their own copy).
		 * senderId: integer 0..2^32-1. recipientPublicKeys: array (1..255) of base64 public keys.
		 * Recipients are indexed by an opaque key id (not by user id) in random order, so a reader cannot
		 * learn who else received the message (BCC stays hidden).
		 *
		 * Binary layout, then base64 (one layer, ASCII-safe for the server to store as opaque text):
		 *   [1] version  [4] sender id (big endian)  [1] recipient count  [24] content nonce
		 *   count x ( [8] key id  [24] nonce  [48] content key boxed for that recipient )
		 *   rest: secretbox(padded JSON {sender, data})
		 */
		encrypt: function (payload, senderId, senderSecret, recipientPublicKeys) {
			if (!Number.isInteger(senderId) || senderId < 0 || senderId > 0xFFFFFFFF) { throw new Error('BAD_SENDER_ID'); }
			if (!recipientPublicKeys.length || recipientPublicKeys.length > MAX_RECIPIENTS) { throw new Error('BAD_RECIPIENT_COUNT'); }

			var contentKey = S.crypto_secretbox_keygen();
			var nonce = S.randombytes_buf(S.crypto_secretbox_NONCEBYTES);
			var plain = pad(S.from_string(JSON.stringify({ sender: senderId, data: payload })));
			var ct = S.crypto_secretbox_easy(plain, nonce, contentKey);

			var list = recipientPublicKeys.map(function (pub) { return { id: keyId(pub), pub: pub }; });
			shuffle(list);
			var seen = {};
			list.forEach(function (r) {
				if (seen[r.id]) { throw new Error('DUPLICATE_RECIPIENT_KEY'); }
				seen[r.id] = true;
			});

			var out = new Uint8Array(HEADER + ENTRY * list.length + ct.length);
			out[0] = FORMAT_VERSION;
			out.set(u32be(senderId), 1);
			out[5] = list.length;
			out.set(nonce, 6);
			list.forEach(function (r, i) {
				var kn = S.randombytes_buf(S.crypto_box_NONCEBYTES);
				var off = HEADER + i * ENTRY;
				out.set(S.from_hex(r.id), off);
				out.set(kn, off + 8);
				out.set(S.crypto_box_easy(contentKey, kn, unb64(r.pub), senderSecret), off + 32);
			});
			out.set(ct, HEADER + ENTRY * list.length);
			S.memzero(contentKey);
			return b64(out);
		},

		/**
		 * Decrypt an armored envelope. `senderPublicB64` must be the sender's public key as known by the reader.
		 * Throws on any tampering, wrong key or sender mismatch.
		 */
		decrypt: function (armored, mySecret, senderPublicB64) {
			var bytes = unb64(armored);
			var h = parseHeader(bytes);
			var myId = S.crypto_generichash(8, S.crypto_scalarmult_base(mySecret));
			var entry = -1;
			for (var i = 0; i < h.count; i++) {
				var off = HEADER + i * ENTRY;
				if (sameBytes(bytes.subarray(off, off + 8), myId)) { entry = off; break; }
			}
			if (entry < 0) { throw new Error('NOT_A_RECIPIENT'); }
			var contentKey = S.crypto_box_open_easy(bytes.slice(entry + 32, entry + ENTRY), bytes.slice(entry + 8, entry + 32), unb64(senderPublicB64), mySecret);
			var plain;
			try {
				plain = JSON.parse(S.to_string(unpad(S.crypto_secretbox_open_easy(bytes.slice(h.ctOffset), h.nonce, contentKey))));
			} finally {
				S.memzero(contentKey);
			}
			if (plain.sender !== h.sender) { throw new Error('SENDER_MISMATCH'); }
			return { sender: plain.sender, data: plain.data };
		},

		/** Sender id written in an armored envelope (unauthenticated until decrypt() succeeds). */
		senderOf: function (armored) { return parseHeader(unb64(armored)).sender; },

		/** Opaque id of a public key as used inside envelopes. */
		keyId: function (publicKeyB64) { return keyId(publicKeyB64); },

		/** Human-comparable fingerprint of a public key (verify it out-of-band to defeat a malicious server). */
		fingerprint: function (publicKeyB64) {
			var hex = S.to_hex(S.crypto_generichash(16, unb64(publicKeyB64)));
			return hex.match(/.{4}/g).join(' ');
		},

		/* ---- server API (opaque storage + CSRF hash) ---- */
		server: {
			getMine: function () {
				return fetch(cfg.endpoint + '/me', { credentials: 'same-origin' }).then(json);
			},
			getPublic: function (ids) {
				return fetch(cfg.endpoint + '/public?ids=' + encodeURIComponent(ids.join(',')), { credentials: 'same-origin' })
					.then(json).then(function (r) { return r.keys; });
			},
			store: function (bundle, hash) {
				var body = new URLSearchParams();
				Object.keys(bundle).forEach(function (k) { body.append(k, bundle[k]); });
				body.append('hash', hash);
				return fetch(cfg.endpoint + '/save', { method: 'POST', credentials: 'same-origin', body: body }).then(json);
			}
		}
	};

	function json(res) {
		return res.json().then(function (data) {
			if (!res.ok) { var e = new Error(data.error || 'HTTP_' + res.status); e.details = data.details; throw e; }
			return data;
		});
	}

	global.IorgianCrypto = api;
})(window);
