using System.ComponentModel.DataAnnotations.Schema;

namespace QualityDocc.Domain.Entities
{
    public class User : BaseEntity
    {
        // Ya no necesitamos el Id aquí, se hereda de BaseEntity

        public string Email { get; set; } = string.Empty;

        public string Username { get; set; } = string.Empty;
        public string PasswordHash { get; set; } = string.Empty;
        // Status (heredado de BaseEntity): true = Activo/Vigente | false = Eliminado/Inactivo

        // Relación con Role
        public int RoleId { get; set; }

        [ForeignKey("RoleId")]
        public virtual Role Role { get; set; } = null!;

        // Relación con Company (Nulleable para el SuperAdmin)
        public int? CompanyId { get; set; }

        [ForeignKey("CompanyId")]
        public virtual Company? Company { get; set; }

    }
}