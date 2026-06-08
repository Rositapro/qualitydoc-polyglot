using QualityDocc.Domain.Entities; // Asegúrate de importar el namespace

namespace QualityDocc.Application.DTOs
{
    public class DocumentDto
    {
        public int Id { get; set; }
        public string Title { get; set; } = string.Empty;
        public string Description { get; set; } = string.Empty;
        public string VersionNumber { get; set; } = "0.1";

        // CAMBIO AQUÍ: Ahora usamos el Enum
        public DocumentStatus CurrentStatus { get; set; } = DocumentStatus.Revision;

        public DateTime ChangeDate { get; set; } = DateTime.Now;
        public string CreatedBy { get; set; } = "Sistema";
    }
}