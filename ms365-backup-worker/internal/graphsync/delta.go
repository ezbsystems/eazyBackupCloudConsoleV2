package graphsync

import (
	"strings"
)

func isDeltaNotSupported(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "delta query is not supported") ||
		strings.Contains(msg, "skip token is not provided")
}
