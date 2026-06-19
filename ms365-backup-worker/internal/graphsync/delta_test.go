package graphsync

import "testing"

func TestIsDeltaNotSupported(t *testing.T) {
	err := errorString(`graph 400 Bad Request: {"error":{"message":"Invalid request. Delta query is not supported by this resource."}}`)
	if !isDeltaNotSupported(err) {
		t.Fatal("expected delta-not-supported detection")
	}
	if isDeltaNotSupported(errorString("graph 500")) {
		t.Fatal("unexpected match for unrelated error")
	}
}

type errorString string

func (e errorString) Error() string { return string(e) }
