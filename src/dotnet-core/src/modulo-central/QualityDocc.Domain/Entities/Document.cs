using System.ComponentModel.DataAnnotations.Schema;

namespace QualityDocc.Domain.Entities
{
    public class Document : BaseEntity
    {
        //Hi
        public string Title { get; set; } = string.Empty;
        public string? Description { get; set; } = string.Empty;

        [ForeignKey("AuthorId")]
        public int AuthorId { get; set; }
        public int CompanyId { get; set; }
        public int IsoId { get; set; }
        public string? RejectionNotes { get; set; }
        // Mantenemos solo el Enum, es seguro y tipado
        public DocumentStatus WorkflowState { get; set; }

        // Propiedades de navegación
        public virtual ICollection<DocumentVersion> Versions { get; set; } = new List<DocumentVersion>();

        [ForeignKey("CompanyId")]
        public virtual Company Company { get; set; } = null!;

        [ForeignKey("IsoId")]
        public virtual Iso Iso { get; set; } = null!;
    }
}