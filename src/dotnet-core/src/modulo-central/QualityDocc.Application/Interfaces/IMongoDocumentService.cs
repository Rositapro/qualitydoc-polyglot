using System.Collections.Generic;
using System.Threading.Tasks;
using QualityDocc.Domain.Entities;

namespace QualityDocc.Application.Interfaces
{
    public interface IMongoDocumentService
    {
        Task SaveApprovedDocumentAsync(Document doc, DocumentVersion version, string authorName, string pdfText);
        Task<List<string>> SearchDocumentsAsync(string query, int companyId);
        Task<List<string>> GetObsoleteVersionsAsync(int documentId, int companyId);
    }
}
