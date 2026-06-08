using Microsoft.EntityFrameworkCore;
using QualityDocc.Application.DTOs;
using QualityDocc.Domain.Entities;
using System.Collections.Generic;
using System.Threading.Tasks;

namespace QualityDocc.Application.Interfaces
{
    public interface IDocumentService
    {
        // 1. Crear un documento nuevo en versión inicial (Draft - 0.1)
        Task<DocumentVersion> CreateDocumentAsync(string title, string fileUrl, string extension, int userId);

        // 2. Botón v++: Incrementar el borrador automáticamente (ej: 0.1 -> 0.2)
        Task<DocumentVersion> IncrementMinorVersionAsync(int documentId, string changeLog, int userId);

        // 3. Botón Aprobar: Cambiar el ciclo a "Approved" y brincar la versión a 1.0 (Notas obligatorias)
        Task<DocumentVersion> ApproveDocumentAsync(int documentId, string approvalNotes, int userId);

        Task<List<DocumentDto>> GetAllDocumentsAsync();
        Task UpdateStatusAsync(int id, DocumentStatus newStatus);
        Task RejectDocumentAsync(int id, string reason);

        // En DocumentService.cs
        
    }
}