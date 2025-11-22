package crypto

import (
	"crypto/aes"
	"crypto/cipher"
	"encoding/base64"
	"errors"
	"fmt"
)

// DecryptAES256CBC decrypts data encrypted with AES-256-CBC using the same format as PHP openssl_encrypt.
// Format: base64(IV[16 bytes] + encrypted_data)
// This matches PHP HelperController::decryptKey() implementation.
func DecryptAES256CBC(encryptedData, key string) (string, error) {
	if len(key) == 0 {
		return "", errors.New("encryption key is empty")
	}

	// Decode base64
	decoded, err := base64.StdEncoding.DecodeString(encryptedData)
	if err != nil {
		return "", fmt.Errorf("base64 decode failed: %w", err)
	}

	// AES-256-CBC uses 16-byte IV
	ivLength := 16
	if len(decoded) < ivLength {
		return "", errors.New("encrypted data too short (missing IV)")
	}

	// Extract IV and ciphertext
	iv := decoded[:ivLength]
	ciphertext := decoded[ivLength:]

	if len(ciphertext) == 0 {
		return "", errors.New("ciphertext is empty")
	}

	// Ensure key is exactly 32 bytes for AES-256
	keyBytes := []byte(key)
	if len(keyBytes) > 32 {
		keyBytes = keyBytes[:32]
	} else if len(keyBytes) < 32 {
		// Pad key to 32 bytes if shorter (PHP openssl_encrypt behavior)
		padded := make([]byte, 32)
		copy(padded, keyBytes)
		keyBytes = padded
	}

	// Create cipher block
	block, err := aes.NewCipher(keyBytes)
	if err != nil {
		return "", fmt.Errorf("create cipher: %w", err)
	}

	// Check ciphertext length is multiple of block size
	if len(ciphertext)%aes.BlockSize != 0 {
		return "", errors.New("ciphertext length is not a multiple of block size")
	}

	// Create CBC mode
	mode := cipher.NewCBCDecrypter(block, iv)

	// Decrypt in-place
	plaintext := make([]byte, len(ciphertext))
	mode.CryptBlocks(plaintext, ciphertext)

	// Remove PKCS7 padding (PHP openssl_encrypt adds this)
	plaintext, err = removePKCS7Padding(plaintext)
	if err != nil {
		return "", fmt.Errorf("remove padding: %w", err)
	}

	return string(plaintext), nil
}

// removePKCS7Padding removes PKCS7 padding from decrypted data.
func removePKCS7Padding(data []byte) ([]byte, error) {
	if len(data) == 0 {
		return nil, errors.New("data is empty")
	}

	// Last byte indicates padding length
	paddingLen := int(data[len(data)-1])
	if paddingLen == 0 || paddingLen > aes.BlockSize {
		return nil, fmt.Errorf("invalid padding length: %d", paddingLen)
	}

	// Verify all padding bytes are the same
	for i := len(data) - paddingLen; i < len(data); i++ {
		if data[i] != byte(paddingLen) {
			return nil, errors.New("invalid padding")
		}
	}

	return data[:len(data)-paddingLen], nil
}

