package graphsync

// List item partition queries use createdDateTime / lastModifiedDateTime filters on
// GET /sites/{siteId}/lists/{listId}/items (not delta). Graph supports these filters
// with ConsistencyLevel: eventual; if a tenant rejects $filter, the worker falls back
// to $orderby=createdDateTime and client-side range checks.
const (
	ListItemSelect           = "id,fields,createdDateTime,lastModifiedDateTime,contentType"
	ListItemExpand           = "fields($select=Title,FileLeafRef,LinkTitle,Name,LinkFilename,Description,Subject)"
	ListPartitionPageSize    = "200"
	ListInventoryStart       = "2010-01-01T00:00:00Z"
	ListPartitionConsistency = "eventual"
)

func ListPartitionHeaders() map[string]string {
	return map[string]string{"ConsistencyLevel": ListPartitionConsistency}
}

func ListCreatedDateTimeFilter(start, end string) string {
	return "createdDateTime ge " + start + " and createdDateTime lt " + end
}

func ListLastModifiedFilter(watermark, start, end string) string {
	filter := "lastModifiedDateTime ge " + watermark
	if start != "" {
		filter += " and createdDateTime ge " + start
	}
	if end != "" {
		filter += " and createdDateTime lt " + end
	}
	return filter
}
