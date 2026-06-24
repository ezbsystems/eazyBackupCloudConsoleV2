package archive

import "testing"

func TestClampParallelExtracts(t *testing.T) {
	tests := []struct {
		in   int
		want int
	}{
		{0, defaultParallelExtracts},
		{-1, defaultParallelExtracts},
		{1, 1},
		{32, 32},
		{64, 64},
		{100, maxParallelExtracts},
	}
	for _, tc := range tests {
		if got := clampParallelExtracts(tc.in); got != tc.want {
			t.Fatalf("clampParallelExtracts(%d) = %d, want %d", tc.in, got, tc.want)
		}
	}
}
