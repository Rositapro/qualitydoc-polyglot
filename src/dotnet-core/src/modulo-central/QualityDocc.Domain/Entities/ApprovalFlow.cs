using System;
using System.ComponentModel.DataAnnotations.Schema;

namespace QualityDocc.Domain.Entities
{
    public class ApprovalFlow : BaseEntity
    {

        public int VersionId { get; set; }
        public int ApproverId { get; set; }

        public string Comments { get; set; } = string.Empty;
        public string Decision { get; set; } = string.Empty;

        // Propiedades de navegación (Entity Framework las usa para hacer joins automáticamente)
        [ForeignKey("VersionId")]
        public virtual DocumentVersion Version { get; set; } = null!;

        [ForeignKey("ApproverId")]
        public virtual User Approver { get; set; } = null!;
    }
}