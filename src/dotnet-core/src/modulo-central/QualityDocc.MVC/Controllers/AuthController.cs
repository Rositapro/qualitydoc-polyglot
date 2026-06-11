using System;
using System.Collections.Generic;
using System.Linq;
using System.Security.Claims;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Authentication;
using Microsoft.AspNetCore.Authentication.Cookies;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityDocc.Infrastructure.Data;

namespace QualityDocc.MVC.Controllers
{
    public class AuthController : Controller
    {
        private readonly ApplicationDbContext _context;

        public AuthController(ApplicationDbContext context)
        {
            _context = context;
        }

        // Muestra la vista de login
        [HttpGet]
        public IActionResult Login()
        {
            return View();
        }

        // Procesa el login
        [HttpPost]
        public async Task<IActionResult> Login(string email, string password)
        {
            // 1. Buscamos al usuario incluyendo su Rol y Empresa
            var user = await _context.User
                        .Include(u => u.Role)
                        .Include(u => u.Company)
                        .FirstOrDefaultAsync(u => u.Email == email);

            // 2. Validación de Empresa Desactivada (Status=false = eliminada/inactiva)
            if (user != null && user.Company != null && user.Company.Status == false)
            {
                ModelState.AddModelError(string.Empty, "La empresa asociada a esta cuenta ha sido desactivada.");
                return View();
            }

            // 3. Validación simple (Ojo: En producción usa PasswordHasher)
            if (user != null && user.PasswordHash == password)
            {
                // 3. Crear las Claims (La "identidad" del usuario)
                var claims = new List<Claim>
                {
                    new Claim(ClaimTypes.Name, user.Username),
                    new Claim(ClaimTypes.NameIdentifier, user.Id.ToString()),
                    new Claim(ClaimTypes.Role, user.Role.Name),
                    new Claim("CompanyId", user.CompanyId?.ToString() ?? "") // Guardamos la empresa aquí
                };

                var claimsIdentity = new ClaimsIdentity(claims, CookieAuthenticationDefaults.AuthenticationScheme);

                // 4. Iniciar sesión (Crea la cookie)
                await HttpContext.SignInAsync(
                    CookieAuthenticationDefaults.AuthenticationScheme,
                    new ClaimsPrincipal(claimsIdentity));

                return RedirectToAction("Index", "Home");
            }

            ModelState.AddModelError(string.Empty, "Usuario o contraseña incorrectos.");
            return View();
        }

        // Cierra la sesión
        [HttpGet]
        public async Task<IActionResult> Logout()
        {
            await HttpContext.SignOutAsync(CookieAuthenticationDefaults.AuthenticationScheme);
            return RedirectToAction("Login");
        }

        // Utilidad para verificar si la BD responde
        [HttpGet]
        public async Task<IActionResult> TestDbConnection()
        {
            try
            {
                bool canConnect = await _context.Database.CanConnectAsync();
                return Content(canConnect ? "¡Conexión establecida!" : "Error: No se pudo conectar.");
            }
            catch (Exception ex)
            {
                return Content($"Error crítico: {ex.Message}");
            }
        }
    }
}