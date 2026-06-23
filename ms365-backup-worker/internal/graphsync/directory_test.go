package graphsync

import (
	"strings"
	"testing"
	"time"
)

func TestDirectoryDeltaSelectFields(t *testing.T) {
	for name, selectFields := range map[string]string{
		"users":  directoryUsersSelect,
		"groups": directoryGroupsSelect,
	} {
		if strings.Contains(selectFields, "lastModifiedDateTime") {
			t.Fatalf("%s $select must not include lastModifiedDateTime: %q", name, selectFields)
		}
	}
	if directoryUsersSelect != "id,displayName,userPrincipalName,mail" {
		t.Fatalf("users select = %q", directoryUsersSelect)
	}
	if directoryGroupsSelect != "id,displayName,mail" {
		t.Fatalf("groups select = %q", directoryGroupsSelect)
	}
}

func TestGraphfsModTimeAbsentField(t *testing.T) {
	for _, v := range []any{nil, "", "not-a-date"} {
		got := graphfsModTime(v)
		if got.IsZero() {
			t.Fatalf("graphfsModTime(%#v) returned zero time", v)
		}
		if got.Location() != time.UTC {
			t.Fatalf("graphfsModTime(%#v) location = %v want UTC", v, got.Location())
		}
	}
}
