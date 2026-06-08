using System.ComponentModel.DataAnnotations.Schema;

namespace QualityDocc.Domain.Entities
{
    [Table("Iso")]
    public class Iso : BaseEntity
    {
        // El Id ya lo heredas de BaseEntity, no hace falta ponerlo aquí

        public string Name { get; set; } = string.Empty;

        // Relación con Empresa
        public int CompanyId { get; set; }

        [ForeignKey("CompanyId")]
        public virtual Company Company { get; set; } = null!;
    }
}
