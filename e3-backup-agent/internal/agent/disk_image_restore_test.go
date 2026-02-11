package agent

import "testing"

func TestBuildRestoreExtentsFromLayout_GlobalOffsets(t *testing.T) {
	layout := &DiskLayout{
		Partitions: []DiskPartition{
			{
				Index:      1,
				StartBytes: 1024,
				UsedExtents: []DiskExtent{
					{OffsetBytes: 100, LengthBytes: 50},
					{OffsetBytes: 1000, LengthBytes: 25},
				},
			},
			{
				Index:      2,
				StartBytes: 10_000,
				UsedExtents: []DiskExtent{
					{OffsetBytes: 0, LengthBytes: 10},
				},
			},
		},
	}

	extents := buildRestoreExtentsFromLayout(layout)
	if len(extents) != 3 {
		t.Fatalf("expected 3 extents, got %d", len(extents))
	}
	if extents[0].OffsetBytes != 1124 || extents[0].LengthBytes != 50 {
		t.Fatalf("unexpected extent[0]: %#v", extents[0])
	}
	if extents[1].OffsetBytes != 2024 || extents[1].LengthBytes != 25 {
		t.Fatalf("unexpected extent[1]: %#v", extents[1])
	}
	if extents[2].OffsetBytes != 10_000 || extents[2].LengthBytes != 10 {
		t.Fatalf("unexpected extent[2]: %#v", extents[2])
	}
}

func TestNormalizeDiskExtents_MergeAndClamp(t *testing.T) {
	in := []DiskExtent{
		{OffsetBytes: 100, LengthBytes: 50},  // 100-150
		{OffsetBytes: 140, LengthBytes: 30},  // overlap => merge to 100-170
		{OffsetBytes: 500, LengthBytes: 700}, // clamp at disk end
		{OffsetBytes: -20, LengthBytes: 40},  // clamp to 0-20
	}

	out := normalizeDiskExtents(in, 1000)
	if len(out) != 3 {
		t.Fatalf("expected 3 merged extents, got %d (%#v)", len(out), out)
	}
	if out[0].OffsetBytes != 0 || out[0].LengthBytes != 20 {
		t.Fatalf("unexpected out[0]: %#v", out[0])
	}
	if out[1].OffsetBytes != 100 || out[1].LengthBytes != 70 {
		t.Fatalf("unexpected out[1]: %#v", out[1])
	}
	if out[2].OffsetBytes != 500 || out[2].LengthBytes != 500 {
		t.Fatalf("unexpected out[2]: %#v", out[2])
	}
}
