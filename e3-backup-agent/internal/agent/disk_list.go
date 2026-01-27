package agent

import "log"

// executeListDisksCommand lists physical disks for disk image selection.
func (r *Runner) executeListDisksCommand(cmd PendingCommand) {
	log.Printf("agent: executing list_disks command %d", cmd.CommandID)

	disks, err := ListDisks()
	if err != nil {
		log.Printf("agent: list_disks command %d failed: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "list_disks failed: "+err.Error())
		return
	}

	result := map[string]any{
		"disks": disks,
		"count": len(disks),
	}

	if err := r.client.ReportBrowseResult(cmd.CommandID, result); err != nil {
		log.Printf("agent: list_disks command %d failed to report: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "report list_disks failed: "+err.Error())
		return
	}

	log.Printf("agent: list_disks command %d completed successfully", cmd.CommandID)
}
