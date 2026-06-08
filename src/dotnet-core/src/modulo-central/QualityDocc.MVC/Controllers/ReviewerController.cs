using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using QualityDocc.Domain.Entities;
using QualityDocc.Infrastructure.Data;
using System;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Json;
using System.Security.Claims;
using System.Threading.Tasks;

namespace QualityDocc.MVC.Controllers
{
    // 👇 2. El candado que protege todo el controlador
    [Authorize(Roles = "Reviewer")]
    public class ReviewerController : Controller
    {
        private readonly ApplicationDbContext _context;
        private readonly IWebHostEnvironment _environment;
        private readonly IConfiguration _configuration;

        public ReviewerController(ApplicationDbContext context, IWebHostEnvironment environment, IConfiguration configuration)
        {
            _context = context;
            _environment = environment;
            _configuration = configuration;
        }

        // --- DASHBOARD (Las 3 tarjetas) ---
        [HttpGet]
        public async Task<IActionResult> Index()
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            var currentUser = await _context.User.FindAsync(int.Parse(userIdString));
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            // Contamos los documentos según el Enum DocumentStatus filtrados por empresa
            ViewBag.Pendientes = await _context.Document.CountAsync(d => d.CompanyId == currentUser.CompanyId && d.WorkflowState == DocumentStatus.Revision);
            ViewBag.Aprobados = await _context.Document.CountAsync(d => d.CompanyId == currentUser.CompanyId && d.WorkflowState == DocumentStatus.Aprobado);
            ViewBag.Devueltos = await _context.Document.CountAsync(d => d.CompanyId == currentUser.CompanyId && d.WorkflowState == DocumentStatus.Rechazado);

            return View();
        }

        // --- LISTA DE PENDIENTES ---
        [HttpGet]
        public async Task<IActionResult> Pending()
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            var currentUser = await _context.User.FindAsync(int.Parse(userIdString));
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            // Traemos solo los documentos en estado Revision de la empresa del revisor
            var docsEnRevision = await _context.Document
                .Include(d => d.Iso)
                .Include(d => d.Versions)
                .Where(d => d.CompanyId == currentUser.CompanyId && d.WorkflowState == DocumentStatus.Revision)
                .ToListAsync();

            return View(docsEnRevision);
        }

        // --- VISTA DETALLADA PARA APROBAR/RECHAZAR ---
        [HttpGet]
        public async Task<IActionResult> Review(int id)
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            var currentUser = await _context.User.FindAsync(int.Parse(userIdString));
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            var doc = await _context.Document
                .Include(d => d.Versions)
                .Include(d => d.Iso)
                .FirstOrDefaultAsync(d => d.Id == id);

            if (doc == null)
            {
                return NotFound();
            }

            if (doc.CompanyId != currentUser.CompanyId)
            {
                return Forbid();
            }

            return View(doc);
        }

        // --- PROCESAMIENTO DE LA DECISIÓN ---
        [HttpPost]
        public async Task<IActionResult> ProcessReview(int id, string actionType, string notes)
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            var currentUser = await _context.User.FindAsync(int.Parse(userIdString));
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            var doc = await _context.Document
                .Include(d => d.Versions)
                .Include(d => d.Iso)
                .FirstOrDefaultAsync(d => d.Id == id);

            if (doc == null)
            {
                return NotFound();
            }

            if (doc.CompanyId != currentUser.CompanyId)
            {
                return Forbid();
            }

            if (actionType == "Aprobar")
            {
                using (var transaction = await _context.Database.BeginTransactionAsync())
                {
                    try
                    {
                        // 1. Cambia estado a Aprobado
                        doc.WorkflowState = DocumentStatus.Aprobado;
                        doc.RejectionNotes = null; // Limpiamos notas si lo aprueba

                        // 2. Obtener la última versión registrada (que se está aprobando)
                        var lastVersion = doc.Versions
                            .OrderByDescending(v => v.VersionNumber)
                            .FirstOrDefault();

                        if (lastVersion != null)
                        {
                            // Si es menor a 1.0 (ej. 0.1, 0.2), la primera aprobación la convierte en la versión 1.0.
                            // Si ya es >= 1.0 (ej. 1.1), se mantiene (no cambia).
                            double oldVersionNum = lastVersion.VersionNumber;
                            if (oldVersionNum < 1.0)
                            {
                                lastVersion.VersionNumber = 1.0;
                            }
                            lastVersion.Status = "Vigente";
                        }

                        // 3. Buscar todas las versiones anteriores de este documento que estén como "Vigente" y cambiarlas a "Obsoleto"
                        var oldVersions = await _context.DocumentVersion
                            .Where(v => v.DocumentId == id && v.Id != (lastVersion != null ? lastVersion.Id : 0) && v.Status == "Vigente")
                            .ToListAsync();

                        foreach (var oldVersion in oldVersions)
                        {
                            oldVersion.Status = "Obsoleto";
                        }

                        await _context.SaveChangesAsync();
                        await transaction.CommitAsync();

                        // Sincronizar el documento aprobado con los otros módulos (PHP y Node.js)
                        await SynchronizeToExternalServices(doc, lastVersion, currentUser.CompanyId ?? 0);
                    }
                    catch (System.Exception)
                    {
                        await transaction.RollbackAsync();
                        throw;
                    }
                }
            }
            else if (actionType == "Rechazar")
            {
                // Validación: Si rechaza, DEBE escribir notas
                if (string.IsNullOrWhiteSpace(notes))
                {
                    ModelState.AddModelError("", "Debes dejar una nota explicando los cambios requeridos.");
                    // Si falla, regresamos a la misma vista con el error. Como la vista necesita la categoría y versiones, cargamos el doc con Includes de nuevo
                    var fullDoc = await _context.Document
                        .Include(d => d.Versions)
                        .Include(d => d.Iso)
                        .FirstOrDefaultAsync(d => d.Id == id);
                    return View("Review", fullDoc);
                }

                // Cambia estado a Devuelto/Rechazado (5)
                doc.WorkflowState = DocumentStatus.Rechazado;
                doc.RejectionNotes = notes;

                // Cambiar el estado de la última versión a "Rechazado"
                var lastVersion = doc.Versions
                    .OrderByDescending(v => v.VersionNumber)
                    .FirstOrDefault();
                if (lastVersion != null)
                {
                    lastVersion.Status = "Rechazado";
                }

                await _context.SaveChangesAsync();
            }

            // Lo regresamos al panel principal (Dashboard)
            return RedirectToAction(nameof(Index));
        }

        [HttpGet]
        [AllowAnonymous]
        public async Task<IActionResult> SyncApprovedDocuments()
        {
            var approvedDocs = await _context.Document
                .Include(d => d.Versions)
                .Include(d => d.Iso)
                .Where(d => d.WorkflowState == DocumentStatus.Aprobado)
                .ToListAsync();

            int syncedCount = 0;
            foreach (var doc in approvedDocs)
            {
                var lastVersion = doc.Versions
                    .OrderByDescending(v => v.VersionNumber)
                    .FirstOrDefault();

                if (lastVersion != null)
                {
                    await SynchronizeToExternalServices(doc, lastVersion, doc.CompanyId);
                    syncedCount++;
                }
            }

            return Content($"Sincronizados {syncedCount} documentos aprobados.");
        }

        private async Task SynchronizeToExternalServices(Document doc, DocumentVersion lastVersion, int companyId)
        {
            try
            {
                // 1. Obtener datos del Autor
                var author = await _context.User.FindAsync(doc.AuthorId);
                var authorName = author?.Username ?? "Autor";

                // 2. Leer archivo y convertir a Base64
                string base64File = "";
                string fileName = "";
                if (lastVersion != null && !string.IsNullOrEmpty(lastVersion.FileUrl))
                {
                    fileName = Path.GetFileName(lastVersion.FileUrl);
                    var filePath = Path.Combine(_environment.WebRootPath, lastVersion.FileUrl.TrimStart('/'));
                    if (System.IO.File.Exists(filePath))
                    {
                        byte[] fileBytes = await System.IO.File.ReadAllBytesAsync(filePath);
                        base64File = Convert.ToBase64String(fileBytes);
                    }
                }

                // 3. URLs de sincronización (configuración con fallbacks)
                var phpUrl = _configuration["PhpServiceUrl"] ?? "http://web-php";
                var searchUrl = _configuration["SearchServiceUrl"] ?? "http://search-service:3000";

                using var client = new HttpClient();

                // 4. Enviar a PHP (PostgreSQL)
                var phpPayload = new
                {
                    titulodocumento = doc.Title,
                    codigo = $"QD-{doc.Id}",
                    version = lastVersion != null ? lastVersion.VersionNumber.ToString("0.0", System.Globalization.CultureInfo.InvariantCulture) : "1.0",
                    idiso = doc.Iso?.Name ?? "ISO 9001",
                    estado = "vigente",
                    empresaid = companyId,
                    archivo_base64 = base64File,
                    nombrearchivo = fileName
                };

                var phpEndpoint = (lastVersion != null && lastVersion.VersionNumber > 1.0)
                    ? "api/actualizar.php"
                    : "api/recibir.php";

                var phpResponse = await client.PostAsJsonAsync($"{phpUrl}/{phpEndpoint}", phpPayload);
                if (!phpResponse.IsSuccessStatusCode)
                {
                    var errorContent = await phpResponse.Content.ReadAsStringAsync();
                    Console.WriteLine($"Error al sincronizar con PHP ({phpEndpoint}): {phpResponse.StatusCode} - {errorContent}");
                }

                // 5. Enviar a Node.js (MongoDB)
                var searchPayload = new
                {
                    title = doc.Title,
                    fileExtension = lastVersion != null ? lastVersion.Extension : ".pdf",
                    empresaid = companyId,
                    textContent = $"{doc.Title} {doc.Description ?? ""} {doc.Iso?.Name ?? ""}",
                    metadata = new
                    {
                        author = authorName,
                        version = lastVersion != null ? lastVersion.VersionNumber.ToString("0.0", System.Globalization.CultureInfo.InvariantCulture) : "1.0",
                        iso = doc.Iso?.Name ?? "ISO 9001"
                    },
                    tags = new[] { "document", doc.Iso?.Name ?? "ISO 9001" }
                };

                var searchResponse = await client.PostAsJsonAsync($"{searchUrl}/api/documents", searchPayload);
                if (!searchResponse.IsSuccessStatusCode)
                {
                    var errorContent = await searchResponse.Content.ReadAsStringAsync();
                    Console.WriteLine($"Error al sincronizar con Node.js: {searchResponse.StatusCode} - {errorContent}");
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Excepción en sincronización: {ex.Message}");
            }
        }
    }
}