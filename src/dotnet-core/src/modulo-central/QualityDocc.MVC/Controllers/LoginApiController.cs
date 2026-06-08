using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityDocc.Infrastructure.Data;
using QualityDocc.MVC.Models; // Asegúrate de tener tu APILoginRequest en esta carpeta
using System.Threading.Tasks;

namespace QualityDocc.MVC.Controllers
{
    // Esta ruta es la que usarán PHP y Node: "tu-dominio.com/api/login"
    [Route("api/login")]
    [ApiController]
    public class LoginApiController : ControllerBase
    {
        private readonly ApplicationDbContext _context;

        public LoginApiController(ApplicationDbContext context)
        {
            _context = context;
        }

        [HttpPost]
        public async Task<IActionResult> Authenticate([FromBody] APILoginRequest request)
        {
            // 1. Validar que no vengan vacíos
            if (string.IsNullOrEmpty(request.Email) || string.IsNullOrEmpty(request.Password))
            {
                return Unauthorized(new { success = false, error = "El correo y la contraseña son requeridos." });
            }

            // 2. Buscar en la base de datos con el rol y empresa incluidos
            var user = await _context.User
                .Include(u => u.Role)
                .Include(u => u.Company)
                .FirstOrDefaultAsync(u => u.Email == request.Email && u.PasswordHash == request.Password);

            // 3. Respuesta Fallida (401 Unauthorized)
            if (user == null)
            {
                return Unauthorized(new { success = false, message = "Credenciales incorrectas." });
            }

            // 4. Validación de Empresa Desactivada (Soft Deleted)
            if (user.Company != null && user.Company.IsDeleted == true)
            {
                return Unauthorized(new { success = false, message = "La empresa asociada a esta cuenta ha sido desactivada." });
            }

            // 4. Respuesta Exitosa (200 OK) - Estructura compatible con PHP y Node
            return Ok(new
            {
                success = true,
                user = new
                {
                    idusuario = user.Id.ToString(),
                    nombreusuario = user.Username,
                    rol = user.Role?.Name ?? "colaborador",
                    empresaid = user.CompanyId ?? 1,
                    empresanombre = user.Company?.Name ?? "Empresa"
                }
            });
        }
    }
}