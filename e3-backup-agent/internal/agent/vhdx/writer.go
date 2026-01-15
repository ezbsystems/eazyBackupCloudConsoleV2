package vhdx

import (
	"fmt"
	"io"
	"os"
)

// Writer is a minimal placeholder that currently writes raw sparse data.
// Future work can replace this with a true VHDX dynamic writer.
type Writer struct {
	dst *os.File
}

func New(path string) (*Writer, error) {
	f, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR|os.O_TRUNC, 0o600)
	if err != nil {
		return nil, err
	}
	return &Writer{dst: f}, nil
}

func (w *Writer) WriteAt(p []byte, off int64) (int, error) {
	if w.dst == nil {
		return 0, fmt.Errorf("writer closed")
	}
	if _, err := w.dst.Seek(off, io.SeekStart); err != nil {
		return 0, err
	}
	return w.dst.Write(p)
}

func (w *Writer) Close() error {
	if w.dst == nil {
		return nil
	}
	err := w.dst.Close()
	w.dst = nil
	return err
}

