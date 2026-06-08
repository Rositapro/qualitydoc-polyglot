using System;
using System.ComponentModel.DataAnnotations.Schema;

namespace QualityDocc.Domain.Entities
{
    [Table("Suggestion")]
    public class Suggestion : BaseEntity
    {
        public int DocumentId { get; set; }
        public string AuthorName { get; set; } = string.Empty;
        public string Comment { get; set; } = string.Empty;
        public int CompanyId { get; set; }

        [ForeignKey("DocumentId")]
        public virtual Document Document { get; set; } = null!;
    }
}
