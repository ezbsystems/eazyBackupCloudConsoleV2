package jobs

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"sort"
	"strings"
	"time"
)

// s3SourceConfig models the minimal fields we expect in decrypted source_config for S3-like sources.
type s3SourceConfig struct {
	Endpoint  string `json:"endpoint"`   // e.g., https://s3.wasabisys.com or empty for AWS
	Region    string `json:"region"`     // e.g., us-east-1
	Bucket    string `json:"bucket"`     // source bucket
	AccessKey string `json:"access_key"` // source access key
	SecretKey string `json:"secret_key"` // source secret key
}

type s3PreflightClassification string

const (
	s3PreflightOK        s3PreflightClassification = "ok"
	s3PreflightAuth      s3PreflightClassification = "auth"
	s3PreflightNotFound  s3PreflightClassification = "not_found"
	s3PreflightNetwork   s3PreflightClassification = "network"
	s3PreflightOtherFail s3PreflightClassification = "other"
)

// preflightS3ListZero performs a signed, lightweight GET ListObjectsV2 with max-keys=0 against the bucket to validate
// credentials and bucket existence without enumerating contents.
func preflightS3ListZero(ctx context.Context, sc s3SourceConfig) (s3PreflightClassification, int, string, error) {
	region := strings.TrimSpace(sc.Region)
	if region == "" {
		region = "us-east-1"
	}

	// Determine scheme/host/path strategy
	scheme := "https"
	host := ""
	path := ""
	query := url.Values{
		"list-type": []string{"2"},
		"max-keys":  []string{"0"},
	}

	endpoint := strings.TrimSpace(sc.Endpoint)
	if endpoint == "" || strings.Contains(endpoint, "amazonaws.com") {
		// Prefer virtual-host style for AWS to avoid path-style restrictions.
		awsHost := fmt.Sprintf("s3.%s.amazonaws.com", region)
		if endpoint != "" {
			// Allow overriding region-specific host if a full endpoint is provided
			if u, err := url.Parse(endpoint); err == nil && u.Host != "" {
				if u.Scheme != "" {
					scheme = u.Scheme
				}
				awsHost = u.Host
			}
		}
		host = fmt.Sprintf("%s.%s", sc.Bucket, awsHost)
		path = "/"
	} else {
		// S3-compatible (e.g., Wasabi): use path-style by default
		u, err := url.Parse(endpoint)
		if err != nil {
			return s3PreflightOtherFail, 0, "invalid_endpoint", fmt.Errorf("invalid endpoint: %w", err)
		}
		if u.Scheme != "" {
			scheme = u.Scheme
		}
		host = u.Host
		path = "/" + sc.Bucket
	}

	reqURL := (&url.URL{Scheme: scheme, Host: host, Path: path, RawQuery: query.Encode()}).String()
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, reqURL, nil)
	if err != nil {
		return s3PreflightOtherFail, 0, "build_request_failed", err
	}

	amzDate := time.Now().UTC().Format("20060102T150405Z")
	dateStamp := amzDate[:8]
	payloadHash := sha256Hex([]byte{})

	// Canonical request
	canonicalURI := path
	canonicalQuery := canonicalQueryString(query)
	canonicalHeaders := fmt.Sprintf("host:%s\nx-amz-content-sha256:%s\nx-amz-date:%s\n", host, payloadHash, amzDate)
	signedHeaders := "host;x-amz-content-sha256;x-amz-date"
	canonicalRequest := strings.Join([]string{
		http.MethodGet,
		canonicalURI,
		canonicalQuery,
		canonicalHeaders,
		signedHeaders,
		payloadHash,
	}, "\n")

	// String to sign
	algorithm := "AWS4-HMAC-SHA256"
	credentialScope := fmt.Sprintf("%s/%s/s3/aws4_request", dateStamp, region)
	stringToSign := strings.Join([]string{
		algorithm,
		amzDate,
		credentialScope,
		sha256Hex([]byte(canonicalRequest)),
	}, "\n")

	// Signature
	signingKey := getSignatureKey(sc.SecretKey, dateStamp, region, "s3")
	signature := hex.EncodeToString(hmacSHA256(signingKey, []byte(stringToSign)))
	authorization := fmt.Sprintf("%s Credential=%s/%s, SignedHeaders=%s, Signature=%s",
		algorithm, sc.AccessKey, credentialScope, signedHeaders, signature)

	// Set headers
	req.Header.Set("Host", host)
	req.Header.Set("x-amz-date", amzDate)
	req.Header.Set("x-amz-content-sha256", payloadHash)
	req.Header.Set("Authorization", authorization)

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return s3PreflightNetwork, 0, "network_error", err
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(io.LimitReader(resp.Body, 4<<10)) // 4KiB for quick diagnostics
	lowBody := strings.ToLower(string(body))

	switch resp.StatusCode {
	case 200, 204:
		return s3PreflightOK, resp.StatusCode, "", nil
	case 404:
		return s3PreflightNotFound, resp.StatusCode, "bucket_not_found", nil
	case 301, 400, 401, 403:
		// Classify as auth; refine detail for common cases
		detail := "auth_failed"
		if strings.Contains(lowBody, "nosuchbucket") {
			return s3PreflightNotFound, resp.StatusCode, "bucket_not_found", nil
		}
		if strings.Contains(lowBody, "signaturedoesnotmatch") {
			detail = "signature_mismatch"
		} else if strings.Contains(lowBody, "invalidaccesskeyid") {
			detail = "invalid_access_key"
		} else if strings.Contains(lowBody, "accessdenied") {
			detail = "access_denied"
		} else if hdr := resp.Header.Get("x-amz-bucket-region"); hdr != "" && !strings.EqualFold(hdr, region) {
			detail = "region_mismatch"
		}
		return s3PreflightAuth, resp.StatusCode, detail, nil
	default:
		return s3PreflightOtherFail, resp.StatusCode, fmt.Sprintf("status_%d", resp.StatusCode), nil
	}
}

func canonicalQueryString(v url.Values) string {
	if v == nil {
		return ""
	}
	keys := make([]string, 0, len(v))
	for k := range v {
		keys = append(keys, k)
	}
	sort.Strings(keys)
	var parts []string
	for _, k := range keys {
		vals := append([]string{}, v[k]...)
		sort.Strings(vals)
		for _, val := range vals {
			parts = append(parts, url.QueryEscape(k)+"="+url.QueryEscape(val))
		}
	}
	return strings.Join(parts, "&")
}

func hmacSHA256(key, data []byte) []byte {
	h := hmac.New(sha256.New, key)
	_, _ = h.Write(data)
	return h.Sum(nil)
}

func sha256Hex(data []byte) string {
	sum := sha256.Sum256(data)
	return hex.EncodeToString(sum[:])
}

func getSignatureKey(secret, dateStamp, region, service string) []byte {
	kDate := hmacSHA256([]byte("AWS4"+secret), []byte(dateStamp))
	kRegion := hmacSHA256(kDate, []byte(region))
	kService := hmacSHA256(kRegion, []byte(service))
	kSigning := hmacSHA256(kService, []byte("aws4_request"))
	return kSigning
}
