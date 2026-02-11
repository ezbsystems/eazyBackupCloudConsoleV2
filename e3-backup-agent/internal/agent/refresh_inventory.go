package agent

import "log"

// executeRefreshInventoryCommand forces an immediate volume inventory report.
func (r *Runner) executeRefreshInventoryCommand(cmd PendingCommand) {
	log.Printf("agent: executing refresh_inventory command %d", cmd.CommandID)

	vols, err := ListVolumes()
	if err != nil {
		log.Printf("agent: refresh_inventory command %d list volumes failed: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "list volumes failed: "+err.Error())
		return
	}

	if err := r.client.ReportVolumes(vols); err != nil {
		log.Printf("agent: refresh_inventory command %d report failed: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "report volumes failed: "+err.Error())
		return
	}

	_ = r.client.CompleteCommand(cmd.CommandID, "completed", "inventory refreshed")
}

