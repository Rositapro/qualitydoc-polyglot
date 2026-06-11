using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using QualityDocc.Domain.Entities;
using QualityDocc.Infrastructure.Data;
using System;
using System.IO;
using System.Linq;
using System.Security.Claims;
using System.Threading.Tasks;

namespace QualityDocc.MVC.Controllers
{
    [Authorize(Roles = "SuperAdmin,Administrator")]
    public class AdminController : Controller
    {
        private readonly ApplicationDbContext _context;
        private readonly IWebHostEnvironment _environment;

        public AdminController(ApplicationDbContext context, IWebHostEnvironment environment)
        {
            _context = context;
            _environment = environment;
        }

        // Helper para obtener el usuario autenticado actual con su Tenant/CompanyId
        private async Task<User?> GetCurrentUserAsync()
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return null;
            return await _context.User.FindAsync(int.Parse(userIdString));
        }

        // 1. DASHBOARD GLOBAL / TENANT
        [HttpGet]
        public async Task<IActionResult> Dashboard()
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            int totalDocuments, totalUsers, totalIsos;

            if (currentUser.CompanyId == null) // SuperAdmin (Sin Empresa asignada)
            {
                totalDocuments = await _context.Document.CountAsync();
                totalUsers = await _context.User.CountAsync(u => u.Status == true);
                totalIsos = await _context.Iso.CountAsync();
            }
            else // Admin de Empresa Específica (Multi-tenant)
            {
                totalDocuments = await _context.Document.CountAsync(d => d.CompanyId == currentUser.CompanyId);
                totalUsers = await _context.User.CountAsync(u => u.CompanyId == currentUser.CompanyId && u.Status == true);
                totalIsos = await _context.Iso.CountAsync(c => c.CompanyId == currentUser.CompanyId);
            }

            // Calcular espacio en disco de wwwroot/uploads (Compartido o filtrado)
            double totalDiskSpaceMB = 0;
            try
            {
                var uploadsFolder = Path.Combine(_environment.WebRootPath, "uploads");
                if (Directory.Exists(uploadsFolder))
                {
                    var dirInfo = new DirectoryInfo(uploadsFolder);
                    long totalBytes = dirInfo.EnumerateFiles("*", SearchOption.AllDirectories).Sum(file => file.Length);
                    totalDiskSpaceMB = Math.Round((double)totalBytes / (1024 * 1024), 2);
                }
            }
            catch (Exception ex)
            {
                ViewBag.DiskError = "No se pudo calcular el espacio en disco: " + ex.Message;
            }

            ViewBag.TotalDocuments = totalDocuments;
            ViewBag.TotalUsers = totalUsers;
            ViewBag.TotalIsos = totalIsos;
            ViewBag.TotalDiskSpaceMB = totalDiskSpaceMB;

            return View();
        }

        // 2. CRUD USUARIOS - LISTADO
        [HttpGet]
        public async Task<IActionResult> Users()
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            IQueryable<User> usersQuery = _context.User
                .Include(u => u.Role)
                .Include(u => u.Company)
                .Where(u => u.Status == true); // Solo usuarios activos/vigentes

            if (currentUser.CompanyId != null) // Filtrado Tenant
            {
                usersQuery = usersQuery.Where(u => u.CompanyId == currentUser.CompanyId);
            }

            var users = await usersQuery.ToListAsync();

            ViewBag.Roles = await _context.Role.ToListAsync();

            if (currentUser.CompanyId == null)
            {
                ViewBag.Companies = await _context.Company.Where(c => c.Status == true).ToListAsync();
            }
            else
            {
                ViewBag.Companies = await _context.Company.Where(c => c.Id == currentUser.CompanyId && c.Status == true).ToListAsync();
            }

            return View(users);
        }

        // CRUD USUARIOS - CREAR
        [HttpPost]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> CreateUser(User user, string plainPassword)
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            if (currentUser.CompanyId != null)
            {
                // Un admin local no puede crear usuarios de otra empresa
                user.CompanyId = currentUser.CompanyId;
            }

            if (string.IsNullOrWhiteSpace(plainPassword))
            {
                TempData["UserError"] = "La contraseña es obligatoria.";
                return RedirectToAction(nameof(Users));
            }

            user.PasswordHash = plainPassword;
            // Status = true por defecto (heredado de BaseEntity)

            _context.User.Add(user);
            await _context.SaveChangesAsync();

            TempData["UserSuccess"] = "Usuario creado exitosamente.";
            return RedirectToAction(nameof(Users));
        }

        // CRUD USUARIOS - OBTENER (Para Modal AJAX)
        [HttpGet]
        public async Task<IActionResult> GetUser(int id)
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return Unauthorized();

            var user = await _context.User.FindAsync(id);
            if (user == null) return NotFound();

            // Validación de límites Tenant
            if (currentUser.CompanyId != null && user.CompanyId != currentUser.CompanyId)
            {
                return Forbid();
            }

            return Json(new { id = user.Id, username = user.Username, email = user.Email, roleId = user.RoleId, companyId = user.CompanyId });
        }

        // CRUD USUARIOS - EDITAR
        [HttpPost]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> EditUser(int id, string username, string email, int roleId, int? companyId, string? plainPassword)
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            var userToEdit = await _context.User.FindAsync(id);
            if (userToEdit == null) return NotFound();

            // Validación de límites Tenant
            if (currentUser.CompanyId != null && userToEdit.CompanyId != currentUser.CompanyId)
            {
                return Forbid();
            }

            userToEdit.Username = username;
            userToEdit.Email = email;
            userToEdit.RoleId = roleId;

            if (currentUser.CompanyId == null)
            {
                userToEdit.CompanyId = companyId;
            }
            else
            {
                userToEdit.CompanyId = currentUser.CompanyId;
            }

            if (!string.IsNullOrWhiteSpace(plainPassword))
            {
                userToEdit.PasswordHash = plainPassword;
            }

            _context.User.Update(userToEdit);
            await _context.SaveChangesAsync();

            TempData["UserSuccess"] = "Usuario actualizado exitosamente.";
            return RedirectToAction(nameof(Users));
        }

        // CRUD USUARIOS - ELIMINAR
        [HttpPost]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> DeleteUser(int id)
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            var userToDelete = await _context.User.FindAsync(id);
            if (userToDelete == null) return NotFound();

            // Validación de límites Tenant
            if (currentUser.CompanyId != null && userToDelete.CompanyId != currentUser.CompanyId)
            {
                return Forbid();
            }

            userToDelete.Status = false;        // Soft Delete: Status=false → Eliminado/Inactivo
            userToDelete.DateDelete = DateTime.Now;
            _context.User.Update(userToDelete);
            await _context.SaveChangesAsync();

            TempData["UserSuccess"] = "Usuario eliminado exitosamente.";
            return RedirectToAction(nameof(Users));
        }

        // 3. CRUD ISOs - LISTADO
        [HttpGet]
        public async Task<IActionResult> Isos()
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            IQueryable<Iso> isosQuery = _context.Iso
                .Include(c => c.Company);

            if (currentUser.CompanyId != null)
            {
                isosQuery = isosQuery.Where(i => i.CompanyId == currentUser.CompanyId);
            }

            var isos = await isosQuery.ToListAsync();

            if (currentUser.CompanyId == null)
            {
                ViewBag.Companies = await _context.Company.Where(c => c.Status == true).ToListAsync();
            }
            else
            {
                ViewBag.Companies = await _context.Company.Where(c => c.Id == currentUser.CompanyId && c.Status == true).ToListAsync();
            }

            return View(isos);
        }

        // CRUD ISOs - CREAR
        [HttpPost]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> CreateIso(Iso iso)
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            if (currentUser.CompanyId != null)
            {
                // Forzar la empresa del admin local
                iso.CompanyId = currentUser.CompanyId.Value;
            }

            if (string.IsNullOrWhiteSpace(iso.Name))
            {
                TempData["IsoError"] = "El nombre de la ISO es obligatorio.";
                return RedirectToAction(nameof(Isos));
            }

            _context.Iso.Add(iso);
            await _context.SaveChangesAsync();

            TempData["IsoSuccess"] = "ISO creada exitosamente.";
            return RedirectToAction(nameof(Isos));
        }

        // CRUD ISOs - EDITAR
        [HttpPost]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> EditIso(int id, string name, int companyId)
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            var iso = await _context.Iso.FindAsync(id);
            if (iso == null) return NotFound();

            // Validación de límites Tenant
            if (currentUser.CompanyId != null && iso.CompanyId != currentUser.CompanyId)
            {
                return Forbid();
            }

            iso.Name = name;

            if (currentUser.CompanyId == null)
            {
                iso.CompanyId = companyId;
            }
            else
            {
                iso.CompanyId = currentUser.CompanyId.Value;
            }

            _context.Iso.Update(iso);
            await _context.SaveChangesAsync();

            TempData["IsoSuccess"] = "ISO actualizada exitosamente.";
            return RedirectToAction(nameof(Isos));
        }

        // CRUD ISOs - ELIMINAR
        [HttpPost]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> DeleteIso(int id)
        {
            var currentUser = await GetCurrentUserAsync();
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            var iso = await _context.Iso.FindAsync(id);
            if (iso == null) return NotFound();

            // Validación de límites Tenant
            if (currentUser.CompanyId != null && iso.CompanyId != currentUser.CompanyId)
            {
                return Forbid();
            }

            _context.Iso.Remove(iso);
            await _context.SaveChangesAsync();

            TempData["IsoSuccess"] = "ISO eliminada exitosamente.";
            return RedirectToAction(nameof(Isos));
        }

        // 4. CRUD EMPRESAS - LISTADO (Solo SuperAdmin)
        [HttpGet]
        [Authorize(Roles = "SuperAdmin")]
        public async Task<IActionResult> Companies()
        {
            var companies = await _context.Company
                .Where(c => c.Status == true) // Solo empresas activas/vigentes
                .ToListAsync();

            return View(companies);
        }

        // CRUD EMPRESAS - CREAR (Solo SuperAdmin)
        [HttpPost]
        [Authorize(Roles = "SuperAdmin")]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> CreateCompany(Company company)
        {
            if (string.IsNullOrWhiteSpace(company.Name))
            {
                TempData["CompanyError"] = "El nombre de la empresa es obligatorio.";
                return RedirectToAction(nameof(Companies));
            }

            // Status = true por defecto (heredado de BaseEntity)
            company.DateCreate = DateTime.Now;

            _context.Company.Add(company);
            await _context.SaveChangesAsync();

            TempData["CompanySuccess"] = "Empresa creada exitosamente.";
            return RedirectToAction(nameof(Companies));
        }

        // CRUD EMPRESAS - EDITAR (Solo SuperAdmin)
        [HttpPost]
        [Authorize(Roles = "SuperAdmin")]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> EditCompany(int id, string name)
        {
            var company = await _context.Company.FindAsync(id);
            if (company == null) return NotFound();

            if (string.IsNullOrWhiteSpace(name))
            {
                TempData["CompanyError"] = "El nombre de la empresa es obligatorio.";
                return RedirectToAction(nameof(Companies));
            }

            company.Name = name;
            company.DateUpdate = DateTime.Now;

            _context.Company.Update(company);
            await _context.SaveChangesAsync();

            TempData["CompanySuccess"] = "Empresa actualizada exitosamente.";
            return RedirectToAction(nameof(Companies));
        }

        // CRUD EMPRESAS - ELIMINAR (Solo SuperAdmin)
        [HttpPost]
        [Authorize(Roles = "SuperAdmin")]
        [ValidateAntiForgeryToken]
        public async Task<IActionResult> DeleteCompany(int id)
        {
            var company = await _context.Company.FindAsync(id);
            if (company == null) return NotFound();

            // Soft delete: Status=false → Empresa eliminada/inactiva
            company.Status = false;
            company.DateDelete = DateTime.Now;

            _context.Company.Update(company);
            await _context.SaveChangesAsync();

            TempData["CompanySuccess"] = "Empresa eliminada exitosamente.";
            return RedirectToAction(nameof(Companies));
        }
    }
}
