package archive

import (
	"context"
	"crypto/tls"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"

	"github.com/minio/minio-go/v7"
	"github.com/minio/minio-go/v7/pkg/credentials"
)

func newMinioClient(endpoint, region, accessKey, secretKey string) (*minio.Client, error) {
	endpointHost := strings.TrimSpace(endpoint)
	secure := true
	if endpointHost != "" {
		if u, err := url.Parse(endpointHost); err == nil && u.Host != "" {
			endpointHost = u.Host
			secure = u.Scheme != "http"
		}
	}
	if endpointHost == "" {
		return nil, fmt.Errorf("dest endpoint required")
	}
	return minio.New(endpointHost, &minio.Options{
		Creds:  credentials.NewStaticV4(accessKey, secretKey, ""),
		Secure: secure,
		Region: region,
		Transport: &http.Transport{
			TLSClientConfig: &tls.Config{InsecureSkipVerify: true}, //nolint:gosec // matches kopia s3 storage
		},
	})
}

func streamPutObject(ctx context.Context, client *minio.Client, bucket, objectKey string, body io.Reader) (int64, error) {
	info, err := client.PutObject(ctx, bucket, objectKey, body, -1, minio.PutObjectOptions{
		ContentType: "application/zip",
	})
	if err != nil {
		return 0, err
	}
	return info.Size, nil
}
