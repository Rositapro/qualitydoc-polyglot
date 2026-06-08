using System;
using System.Linq;
using System.Threading.Tasks;
using Microsoft.EntityFrameworkCore;
using QualityDocc.Application.Interfaces;
using QualityDocc.Domain.Entities;
using QualityDocc.Infrastructure.Data;
using QualityDocc.Application.DTOs;

namespace QualityDocc.Application.Services
{
    public class DocumentService : IDocumentService
    {
        // CAMBIO 1: Usamos el contexto específico (ApplicationDbContext)
        private readonly ApplicationDbContext _context;

        public DocumentService(ApplicationDbContext context)
        {
            _context = context;
        }

        // CAMBIO 2: Crear el contenedor "Documento" primero
        public async Task<DocumentVersion> CreateDocumentAsync(string title, string fileUrl, string extension, int userId)
        {
            var newDocument = new Document
            {
                Title = title,
                WorkflowState = DocumentStatus.Revision, // Usamos esto, no LifecycleStatus
                AuthorId = userId
            };
            _context.Document.Add(newDocument);
            await _context.SaveChangesAsync();

            var initialVersion = new DocumentVersion
            {
                DocumentId = newDocument.Id,
                VersionNumber = 0.1, // Versión inicial en borrador
                FileUrl = fileUrl,
                Extension = extension,
                ChangeLog = "Creación inicial.",
                IdUserCreate = userId,
                DateCreate = DateTime.Now
            };

            _context.Set<DocumentVersion>().Add(initialVersion);
            await _context.SaveChangesAsync();
            return initialVersion;
        }

        public async Task<DocumentVersion> ApproveDocumentAsync(int documentId, string approvalNotes, int userId)
        {
            var lastVersion = await _context.Set<DocumentVersion>()
                .Where(v => v.DocumentId == documentId)
                .OrderByDescending(v => v.VersionNumber)
                .FirstOrDefaultAsync();

            if (lastVersion == null)
            {
                throw new InvalidOperationException("No se encontró ninguna versión para aprobar.");
            }

            // Si es menor a 1.0 (ej. 0.1, 0.2), la primera aprobación la convierte en la versión 1.0.
            // Si ya es >= 1.0 (ej. 1.1), se mantiene (no cambia).
            if (lastVersion.VersionNumber < 1.0)
            {
                lastVersion.VersionNumber = 1.0;
            }

            lastVersion.Status = "Vigente";
            lastVersion.ChangeLog = "APROBADO: " + approvalNotes;

            // Buscar todas las versiones anteriores de este documento que estén como "Vigente" y cambiarlas a "Obsoleto"
            var oldVersions = await _context.Set<DocumentVersion>()
                .Where(v => v.DocumentId == documentId && v.Id != lastVersion.Id && v.Status == "Vigente")
                .ToListAsync();

            foreach (var oldVersion in oldVersions)
            {
                oldVersion.Status = "Obsoleto";
            }

            var doc = await _context.Document.FindAsync(documentId);
            if (doc != null)
            {
                doc.WorkflowState = DocumentStatus.Aprobado;
                doc.RejectionNotes = null;
            }

            await _context.SaveChangesAsync();
            return lastVersion;
        }

        public async Task UpdateStatusAsync(int id, DocumentStatus newStatus)
        {
            var document = await _context.Document.FindAsync(id);
            if (document != null)
            {
                document.WorkflowState = newStatus;
                await _context.SaveChangesAsync();
            }
        }

        public async Task RejectDocumentAsync(int id, string reason)
        {
            var document = await _context.Document.FindAsync(id);
            if (document != null)
            {
                document.WorkflowState = DocumentStatus.Rechazado;
                document.RejectionNotes = reason;
                await _context.SaveChangesAsync();
            }
        }

        public async Task<List<DocumentDto>> GetAllDocumentsAsync()
        {
            var documents = await _context.Document
                .Include(d => d.Versions)
                .ToListAsync();

            return documents.Select(d =>
            {
                var latestVersion = d.Versions
                    .OrderByDescending(v => v.VersionNumber)
                    .FirstOrDefault();

                return new DocumentDto
                {
                    Id = d.Id,
                    Title = d.Title,
                    Description = d.Description ?? string.Empty,
                    VersionNumber = latestVersion != null 
                        ? latestVersion.VersionNumber.ToString("0.0", System.Globalization.CultureInfo.InvariantCulture) 
                        : "0.1",
                    CurrentStatus = d.WorkflowState,
                    ChangeDate = latestVersion?.DateCreate ?? d.DateCreate ?? DateTime.Now,
                    CreatedBy = "Sistema"
                };
            }).ToList();
        }

        public async Task<DocumentVersion> IncrementMinorVersionAsync(int documentId, string changeLog, int userId)
        {
            var lastVersion = await _context.Set<DocumentVersion>()
                .Where(v => v.DocumentId == documentId)
                .OrderByDescending(v => v.VersionNumber)
                .FirstOrDefaultAsync();

            var newVersionNumber = (lastVersion != null) 
                ? Math.Round(lastVersion.VersionNumber + 0.1, 1) 
                : 0.1;

            var newVersion = new DocumentVersion
            {
                DocumentId = documentId,
                VersionNumber = newVersionNumber,
                FileUrl = lastVersion?.FileUrl ?? "URL_default",
                Extension = lastVersion?.Extension ?? ".pdf",
                ChangeLog = changeLog,
                IdUserCreate = userId,
                DateCreate = DateTime.Now,
                Status = "En Revisión"
            };

            _context.Set<DocumentVersion>().Add(newVersion);

            var doc = await _context.Document.FindAsync(documentId);
            if (doc != null)
            {
                doc.WorkflowState = DocumentStatus.Revision;
            }

            await _context.SaveChangesAsync();
            return newVersion;
        }

        public double GetNextVersionNumber(int documentId)
        {
            var lastVersion = _context.DocumentVersion
                .Where(v => v.DocumentId == documentId)
                .OrderByDescending(v => v.VersionNumber)
                .FirstOrDefault();

            if (lastVersion == null) return 0.1;

            return Math.Round(lastVersion.VersionNumber + 0.1, 1);
        }
    }
}