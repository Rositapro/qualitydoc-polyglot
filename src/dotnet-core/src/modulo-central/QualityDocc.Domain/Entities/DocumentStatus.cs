using System;
using System.Collections.Generic;
using System.Text;

namespace QualityDocc.Domain.Entities
{
    
        public enum DocumentStatus
        {
            Revision = 1,
            Aprobado = 2,
            Vigente = 3,
            Obsoleto = 4,
            Rechazado = 5, // ¡Crucial para tu flujo!
            EnAutorizacion = 6
        }
    
}
