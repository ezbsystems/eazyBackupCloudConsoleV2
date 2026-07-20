package graphfs

import (
	"context"
	"sort"

	kopiafs "github.com/kopia/kopia/fs"
)

type sliceIterator struct {
	entries []kopiafs.Entry
	i       int
}

func newSliceIterator(entries []kopiafs.Entry) *sliceIterator {
	return &sliceIterator{entries: entries}
}

func (it *sliceIterator) Next(context.Context) (kopiafs.Entry, error) {
	if it.i >= len(it.entries) {
		return nil, nil
	}
	e := it.entries[it.i]
	it.i++
	return e, nil
}

func (it *sliceIterator) Close() {}

func sortedEntries(children map[string]kopiafs.Entry) []kopiafs.Entry {
	names := make([]string, 0, len(children))
	for n := range children {
		names = append(names, n)
	}
	sort.Strings(names)
	out := make([]kopiafs.Entry, 0, len(names))
	for _, n := range names {
		out = append(out, children[n])
	}
	return out
}
