//go:build !windows

package main

import "fmt"

func main() {
	fmt.Println("e3-backup-tray is only supported on Windows.")
}


