package graphrestore

import "strings"

// isDuplicateGraphError reports whether a Graph write failed because the item
// already exists. With skip_duplicates we treat these as a successful skip.
func isDuplicateGraphError(err error) bool {
	if err == nil {
		return false
	}
	s := strings.ToLower(err.Error())
	for _, needle := range []string{
		"duplicate",
		"already exists",
		"errornameexists",
		"erroritemexists",
		"errorirresolvableconflict",
		"conflict",
	} {
		if strings.Contains(s, needle) {
			return true
		}
	}
	return false
}
