package api

import (
	"context"
	"encoding/json"
	"net"
	"net/http"
	"strings"
)

type Mounter interface {
	Mount(ctx context.Context, req MountRequest) error
	Unmount(ctx context.Context, driveLetter string) error
	List() []MountStatus
}

type Server struct {
	mounter Mounter
	token   string
	version string
	mux     *http.ServeMux
}

func NewServer(mounter Mounter, token string, version string) *Server {
	s := &Server{
		mounter: mounter,
		token:   token,
		version: version,
		mux:     http.NewServeMux(),
	}
	s.mux.HandleFunc("GET /health", s.requireAuth(s.handleHealth))
	s.mux.HandleFunc("GET /status", s.requireAuth(s.handleStatus))
	s.mux.HandleFunc("POST /mount", s.requireAuth(s.handleMount))
	s.mux.HandleFunc("POST /unmount", s.requireAuth(s.handleUnmount))
	return s
}

func (s *Server) Handler() http.Handler {
	return s.mux
}

func (s *Server) Listen() (net.Listener, error) {
	return net.Listen("tcp", "127.0.0.1:0")
}

func (s *Server) Serve(listener net.Listener) error {
	server := &http.Server{Handler: s.Handler()}
	return server.Serve(listener)
}

func (s *Server) requireAuth(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get(TokenHeader) != s.token {
			writeJSON(w, http.StatusUnauthorized, ErrorResponse{Error: "unauthorized"})
			return
		}
		next(w, r)
	}
}

func (s *Server) handleHealth(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, HealthResponse{
		OK:      true,
		Version: s.version,
		WinFsp:  false,
	})
}

func (s *Server) handleStatus(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, StatusResponse{Mounts: s.mounter.List()})
}

func (s *Server) handleMount(w http.ResponseWriter, r *http.Request) {
	var req MountRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, ErrorResponse{Error: "invalid JSON"})
		return
	}
	if strings.TrimSpace(req.DriveLetter) == "" {
		writeJSON(w, http.StatusBadRequest, ErrorResponse{Error: "drive_letter is required"})
		return
	}

	if err := s.mounter.Mount(r.Context(), req); err != nil {
		writeJSON(w, http.StatusBadRequest, ErrorResponse{Error: err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, OKResponse{OK: true})
}

func (s *Server) handleUnmount(w http.ResponseWriter, r *http.Request) {
	var req UnmountRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, ErrorResponse{Error: "invalid JSON"})
		return
	}

	driveLetter := strings.TrimSpace(req.DriveLetter)
	if driveLetter == "" && req.MountID != 0 {
		for _, status := range s.mounter.List() {
			if status.MountID == req.MountID {
				driveLetter = status.DriveLetter
				break
			}
		}
	}
	if driveLetter == "" {
		writeJSON(w, http.StatusBadRequest, ErrorResponse{Error: "drive_letter or mount_id is required"})
		return
	}

	if err := s.mounter.Unmount(r.Context(), driveLetter); err != nil {
		writeJSON(w, http.StatusBadRequest, ErrorResponse{Error: err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, OKResponse{OK: true})
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}
