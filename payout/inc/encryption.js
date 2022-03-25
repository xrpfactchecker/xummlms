//https://gist.github.com/Tiriel/bff8b06cb3359bba5f9e9ba1f9fc52c0

'use strict';

const crypto = require('crypto');

const AES_METHOD = 'aes-256-cbc';
const IV_LENGTH = 16; // For AES, this is always 16, checked with php

const password = 'lbwyBzfgzUIvXZFShJuikaWvLJhIVq36'; // Must be 256 bytes (32 characters)

module.exports = {
	encrypt: (text, password) => {
		if (process.versions.openssl <= '1.0.1f') {
			throw new Error('OpenSSL Version too old, vulnerability to Heartbleed');
		}

		let iv = crypto.randomBytes(IV_LENGTH);
		let cipher = crypto.createCipheriv(AES_METHOD, Buffer.from(password), iv);
		let encrypted = cipher.update(text);

		encrypted = Buffer.concat([encrypted, cipher.final()]);

		return iv.toString('hex') + ':' + encrypted.toString('hex');
	},
	decrypt: (text, password) => {
		let textParts = text.split(':');
		let iv = Buffer.from(textParts.shift(), 'hex');
		let encryptedText = Buffer.from(textParts.join(':'), 'hex');
		let decipher = crypto.createDecipheriv(AES_METHOD, Buffer.from(password), iv);
		let decrypted = decipher.update(encryptedText);

		decrypted = Buffer.concat([decrypted, decipher.final()]);

		return decrypted.toString();
	}
};