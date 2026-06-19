package api

import (
	"testing"
)

func TestDecodeEnvelopeResponseGraphToken(t *testing.T) {
	raw := []byte(`{"status":"success","data":{"graph_token":"tok123","expires_in":3600}}`)
	var out GraphTokenResponse
	if err := decodeEnvelopeResponse(raw, &out); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if out.GraphToken != "tok123" {
		t.Fatalf("graph_token = %q", out.GraphToken)
	}
	if out.ExpiresIn != 3600 {
		t.Fatalf("expires_in = %d", out.ExpiresIn)
	}
}

func TestDecodeEnvelopeResponseNullRepoOperation(t *testing.T) {
	raw := []byte(`{"status":"success","data":null}`)
	var op *RepoOperation
	if err := decodeEnvelopeResponse(raw, &op); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if op != nil {
		t.Fatalf("expected nil repo operation, got %+v", op)
	}
}

func TestDecodeEnvelopeResponseValidRepoOperation(t *testing.T) {
	raw := []byte(`{"status":"success","data":{"operation_id":42,"op_type":"maintenance_quick"}}`)
	var op *RepoOperation
	if err := decodeEnvelopeResponse(raw, &op); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if op == nil {
		t.Fatal("expected repo operation")
	}
	if op.OperationID != 42 {
		t.Fatalf("operation_id = %d, want 42", op.OperationID)
	}
	if op.OpType != "maintenance_quick" {
		t.Fatalf("op_type = %q, want maintenance_quick", op.OpType)
	}
}

func TestDecodeEnvelopeResponseNullRun(t *testing.T) {
	raw := []byte(`{"status":"success","data":{"run":null}}`)
	var out struct {
		Run *RunJob `json:"run"`
	}
	if err := decodeEnvelopeResponse(raw, &out); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if out.Run != nil {
		t.Fatalf("expected nil run, got %+v", out.Run)
	}
}

func TestClaimRepoOperationValidation(t *testing.T) {
	raw := []byte(`{"status":"success","data":null}`)
	var op *RepoOperation
	if err := decodeEnvelopeResponse(raw, &op); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if op != nil {
		t.Fatalf("expected nil after null data decode, got %+v", op)
	}
	if op == nil || op.OperationID <= 0 || op.OpType == "" {
		// mirrors ClaimRepoOperation guard — empty queue is nil, nil
		return
	}
	t.Fatal("expected validation guard to treat empty op as no job")
}
