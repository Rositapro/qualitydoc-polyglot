using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Hosting;
using Microsoft.EntityFrameworkCore;
using System.Security.Claims; // Necesario para obtener los datos del usuario logueado
using System.IO;
using System.Linq;
using System.Threading.Tasks;
using System;
using QualityDocc.Domain.Entities;
using QualityDocc.Infrastructure.Data;
using QualityDocc.MVC.Models.ViewModels;
using Microsoft.AspNetCore.Authorization; // 👇 1. Nueva librería de seguridad

namespace QualityDocc.MVC.Controllers
{
    // 👇 2. El candado que protege el controlador completo
    [Authorize(Roles = "Author")]
    public class AuthorController : Controller
    {
        private readonly ApplicationDbContext _context;
        private readonly IWebHostEnvironment _environment;

        public AuthorController(ApplicationDbContext context, IWebHostEnvironment environment)
        {
            _context = context;
            _environment = environment;
        }

        [HttpGet]
        public async Task<IActionResult> Index()
        {
            var currentUsername = User.Identity?.Name;

            if (string.IsNullOrEmpty(currentUsername))
            {
                return RedirectToAction("Login", "Account");
            }

            var currentUser = _context.User.FirstOrDefault(u => u.Username == currentUsername || u.Email == currentUsername);
            if (currentUser == null)
            {
                return NotFound($"Usuario no encontrado. El sistema está buscando: '{currentUsername}'");
            }

            var totalBorradores = _context.Document
                .Count(d => d.AuthorId == currentUser.Id && d.Status == true && d.WorkflowState == DocumentStatus.Revision);

            var totalAprobados = _context.Document
                .Count(d => d.AuthorId == currentUser.Id && d.Status == true && d.WorkflowState == DocumentStatus.Aprobado);

            var totalDevueltos = _context.Document
                .Count(d => d.AuthorId == currentUser.Id && d.Status == true && d.WorkflowState == DocumentStatus.Rechazado);

            var ultimosArchivos = _context.Document
                .Where(d => d.AuthorId == currentUser.Id && d.Status == true && d.WorkflowState == DocumentStatus.Revision)
                .OrderByDescending(d => d.DateCreate)
                .Take(6)
                .ToList();

            var viewModel = new AuthorDashboardViewModel
            {
                TotalBorradores = totalBorradores,
                TotalAprobados = totalAprobados,
                TotalDevueltos = totalDevueltos,
                UltimosBorradores = ultimosArchivos
            };

            return View(viewModel);
        }

        [HttpGet]
        public async Task<IActionResult> Upload(int? id)
        {
            // 1. Obtenemos el usuario actual y los datos de su empresa
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            int currentUserId = int.Parse(userIdString);

            var user = await _context.User.Include(u => u.Company).FirstOrDefaultAsync(u => u.Id == currentUserId);
            if (user == null) return NotFound();

            // 2. Llenamos los ViewBags necesarios para los dropdowns y etiquetas de la vista
            ViewBag.Isos = await _context.Iso.ToListAsync();
            ViewBag.CompanyName = user.Company?.Name;
            ViewBag.CompanyId = user.CompanyId;

            // 🌟 CASO A: SI TRAE UN ID, VAMOS A CORREGIR UN DOCUMENTO EXISTENTE
            if (id.HasValue && id > 0)
            {
                // Buscamos el documento original en la base de datos
                var docEncontrado = await _context.Document.FindAsync(id);

                // Verificación de seguridad 1: que exista
                if (docEncontrado == null) return NotFound();

                // Verificación de seguridad 2: que pertenezca al autor logueado
                if (docEncontrado.AuthorId != currentUserId)
                {
                    // Guardamos el mensaje de error para mostrarlo en la vista
                    TempData["ErrorMessage"] = "Acceso denegado: No tienes permiso para editar un documento que le pertenece a otro autor.";

                    // Lo regresamos a la pantalla de búsqueda
                    return RedirectToAction("Search");
                }

                // Buscamos la última versión registrada de este documento en el historial
                var lastVersion = await _context.DocumentVersion
                    .Where(v => v.DocumentId == id)
                    .OrderByDescending(v => v.VersionNumber)
                    .FirstOrDefaultAsync();

                // Lógica de incremento automático
                double nextVersion = 0.1;
                if (lastVersion != null)
                {
                    if (docEncontrado.WorkflowState == DocumentStatus.Rechazado ||
                        docEncontrado.WorkflowState == DocumentStatus.Aprobado ||
                        docEncontrado.WorkflowState == DocumentStatus.Vigente)
                    {
                        nextVersion = lastVersion.VersionNumber + 0.1;
                    }
                    else
                    {
                        nextVersion = lastVersion.VersionNumber;
                    }
                }

                // Redondeamos a un decimal para evitar errores de precisión de punto flotante
                ViewBag.SuggestedVersion = Math.Round(nextVersion, 1);

                // Pasamos el documento encontrado a la vista para que los inputs se rellenen solos
                return View(docEncontrado);
            }

            // 🌟 CASO B: SI NO TRAE ID, ES UN DOCUMENTO COMPLETAMENTE NUEVO
            ViewBag.SuggestedVersion = 0.1;
            return View(new Document()); // Pasamos un objeto vacío listo para llenarse
        }

        [HttpPost]
        public async Task<IActionResult> Upload(Document model, IFormFile archivo, string action, double versionNumber)
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            int currentUserId = int.Parse(userIdString);

            var user = await _context.User.Include(u => u.Company).FirstOrDefaultAsync(u => u.Id == currentUserId);
            if (user == null) return NotFound();

            ViewBag.Isos = await _context.Iso.ToListAsync();

            if (archivo == null || archivo.Length == 0)
            {
                ModelState.AddModelError("", "Por favor selecciona un archivo.");
                ViewBag.CompanyName = user?.Company?.Name;
                return View(model);
            }

            string extension = Path.GetExtension(archivo.FileName).ToLower();

            var uniqueFileName = Guid.NewGuid().ToString() + extension;
            var uploadsFolder = Path.Combine(_environment.WebRootPath, "uploads");
            var filePath = Path.Combine(uploadsFolder, uniqueFileName);

            using (var transaction = await _context.Database.BeginTransactionAsync())
            {
                try
                {
                    // 0. Obtener el estado previo del documento si ya existe en la base de datos
                    DocumentStatus? previousState = null;
                    if (model.Id > 0)
                    {
                        var existingDoc = await _context.Document.AsNoTracking().FirstOrDefaultAsync(d => d.Id == model.Id);
                        if (existingDoc != null)
                        {
                            previousState = existingDoc.WorkflowState;
                        }
                    }

                    // 1. PREPARA EL DOCUMENTO Y LLENA LOS DATOS DE AUDITORÍA
                    model.AuthorId = currentUserId;
                    model.CompanyId = user.CompanyId ?? 0;
                    model.Status = true;

                    // Siempre se envía a revisión
                    model.WorkflowState = DocumentStatus.Revision;

                    // 👇 3. LÓGICA DE AGREGAR VS ACTUALIZAR (Para no duplicar borradores)
                    if (model.Id == 0)
                    {
                        model.DateCreate = DateTime.Now;
                        _context.Document.Add(model);
                    }
                    else
                    {
                        // Si el ID ya existe, actualizamos el registro
                        _context.Document.Update(model);
                    }

                    await _context.SaveChangesAsync();

                    // 2. LÓGICA DE CÁLCULO DE LA VERSIÓN EN EL SERVIDOR
                    double finalVersionNumber = 0.1;
                    if (model.Id > 0)
                    {
                        var lastVersion = await _context.DocumentVersion
                            .Where(v => v.DocumentId == model.Id)
                            .OrderByDescending(v => v.VersionNumber)
                            .FirstOrDefaultAsync();

                        if (lastVersion != null)
                        {
                            if (previousState.HasValue && (previousState == DocumentStatus.Rechazado ||
                                                           previousState == DocumentStatus.Aprobado ||
                                                           previousState == DocumentStatus.Vigente))
                            {
                                finalVersionNumber = Math.Round(lastVersion.VersionNumber + 0.1, 1);
                            }
                            else
                            {
                                finalVersionNumber = lastVersion.VersionNumber;
                            }
                        }
                    }

                    // 3. GUARDA LA VERSIÓN
                    var version = new DocumentVersion
                    {
                        DocumentId = model.Id,
                        VersionNumber = finalVersionNumber,
                        FileUrl = "/uploads/" + uniqueFileName,
                        Extension = extension,
                        IdUserCreate = currentUserId,
                        DateCreate = DateTime.Now,
                        ChangeLog = Request.Form["ChangeLog"],
                        Status = action == "save" ? "Borrador" : "En Revisión"
                    };

                    _context.DocumentVersion.Add(version);
                    await _context.SaveChangesAsync();

                    // 3. GUARDADO FÍSICO DEL ARCHIVO
                    if (!Directory.Exists(uploadsFolder)) Directory.CreateDirectory(uploadsFolder);
                    using (var stream = new FileStream(filePath, FileMode.Create))
                    {
                        await archivo.CopyToAsync(stream);
                    }

                    await transaction.CommitAsync();
                }
                catch (Exception ex)
                {
                    await transaction.RollbackAsync();

                    string errorMessage = ex.Message;
                    if (ex.InnerException != null)
                    {
                        errorMessage += " | Inner: " + ex.InnerException.Message;
                    }

                    ModelState.AddModelError("", "Error: " + errorMessage);

                    var userEx = await _context.User.Include(u => u.Company).FirstOrDefaultAsync(u => u.Id == currentUserId);
                    ViewBag.CompanyName = userEx?.Company?.Name;

                    return View(model);
                }
            }

            // 👇 4. LA DECISIÓN DE REDIRECCIÓN
            if (action == "save")
            {
                // Volvemos a cargar la información de la empresa porque recargaremos la misma pantalla
                ViewBag.CompanyName = user?.Company?.Name;
                ViewBag.CompanyId = user?.CompanyId;

                ModelState.Clear(); //Evitar duplicados

                TempData["Message"] = "Borrador guardado. Puedes seguir editando y luego enviarlo.";
                return View(model);
            }

            // Si presionó Enviar
            TempData["Message"] = "Documento enviado al Autorizador correctamente.";
            return RedirectToAction("Index");
        }

        [HttpGet]
        public async Task<IActionResult> Search(int? isoId)
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            int currentUserId = string.IsNullOrEmpty(userIdString) ? 0 : int.Parse(userIdString);

            var currentUser = await _context.User.FindAsync(currentUserId);
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            ViewBag.Isos = await _context.Iso.ToListAsync();

            var query = _context.Document
                                .Include(d => d.Iso)
                                .Where(d => d.CompanyId == currentUser.CompanyId)
                                .AsQueryable();

            if (isoId.HasValue && isoId > 0)
            {
                query = query.Where(d => d.IsoId == isoId);
            }

            query = query.Where(d =>
                d.WorkflowState == DocumentStatus.Aprobado ||
                d.AuthorId == currentUserId
            );

            var documentos = await query.ToListAsync();
            return View(documentos);
        }

        [HttpPost]
        public async Task<IActionResult> SendToReview(int documentId)
        {
            var doc = await _context.Document.FindAsync(documentId);

            if (doc != null)
            {
                doc.WorkflowState = DocumentStatus.Revision;
                await _context.SaveChangesAsync();
            }

            return RedirectToAction("Index");
        }

        [HttpGet]
        public async Task<IActionResult> Returned()
        {
            // 1. Obtenemos el ID del usuario autenticado actual
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            int currentUserId = int.Parse(userIdString);

            // 2. Traemos de la base de datos los documentos devueltos
            // NOTA: Si tu propiedad de estado se llama diferente a "WorkflowState", cámbiala aquí.
            // El número 3 representa el estado 'Devuelto' o 'Rechazado por Revisor'.
            var devueltos = await _context.Document
                .Include(d => d.Iso) // Cargamos la ISO para mostrar su nombre
                .Where(d => d.AuthorId == currentUserId && d.WorkflowState == DocumentStatus.Rechazado)
                .OrderByDescending(d => d.DateCreate)
                .ToListAsync();

            // 3. Enviamos la lista de documentos a la vista Returned.cshtml
            return View(devueltos);
        }

        [HttpGet]
        public async Task<IActionResult> Suggestions()
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            int currentUserId = int.Parse(userIdString);

            // Cargar las sugerencias recibidas para los documentos del autor logueado
            var suggestions = await _context.Suggestions
                .Include(s => s.Document)
                .Where(s => s.Document.AuthorId == currentUserId && s.Status == true)
                .OrderByDescending(s => s.DateCreate)
                .ToListAsync();

            return View(suggestions);
        }
    }
}