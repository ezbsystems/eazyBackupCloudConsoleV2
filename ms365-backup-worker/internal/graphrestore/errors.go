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
		"errormessageitemalreadyexists",
		"namealreadyexists",
		"conflict",
		"status code 409",
		"graph 409",
	} {
		if strings.Contains(s, needle) {
			return true
		}
	}
	return false
}

func isGraphNotFound(err error) bool {
	if err == nil {
		return false
	}
	s := strings.ToLower(err.Error())
	return strings.Contains(s, "graph 404") || strings.Contains(s, "itemnotfound") || strings.Contains(s, "not found")
}
