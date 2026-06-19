package api

import (
	"encoding/json"
	"strings"
)

// ScopeFlags decodes worker claim scope as bool flags, ignoring internal metadata keys.
type ScopeFlags map[string]bool

func (s *ScopeFlags) UnmarshalJSON(data []byte) error {
	var raw map[string]json.RawMessage
	if err := json.Unmarshal(data, &raw); err != nil {
		return err
	}
	out := make(ScopeFlags, len(raw))
	for key, value := range raw {
		if strings.HasPrefix(key, "_") {
			continue
		}
		var flag bool
		if err := json.Unmarshal(value, &flag); err != nil {
			var num int
			if err2 := json.Unmarshal(value, &num); err2 != nil {
				continue
			}
			flag = num != 0
		}
		out[key] = flag
	}
	*s = out
	return nil
}
