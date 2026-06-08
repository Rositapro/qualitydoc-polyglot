using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Configuration;
using QualityDocc.Application.Interfaces;
using QualityDocc.Domain.Entities;
using QualityDocc.Infrastructure.Data;
using QualityDocc.Infrastructure.Utilities;
using System;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Json;
using System.Security.Claims;
using System.Threading.Tasks;

namespace QualityDocc.MVC.Controllers
{
    [Authorize(Roles = "Approver")]
    public class ApproverController : Controller
    {
        private readonly ApplicationDbContext _context;
        private readonly IWebHostEnvironment _environment;
        private readonly IConfiguration _configuration;
        private readonly IMongoDocumentService _mongoService;

        public ApproverController(
            ApplicationDbContext context, 
            IWebHostEnvironment environment, 
            IConfiguration configuration,
            IMongoDocumentService mongoService)
        {
            _context = context;
            _environment = environment;
            _configuration = configuration;
            _mongoService = mongoService;
        }

        // --- DASHBOARD (Las 3 tarjetas del Aprobador) ---
        [HttpGet]
        public async Task<IActionResult> Index()
        {
            var userIdString = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (string.IsNullOrEmpty(userIdString)) return RedirectToAction("Login", "Auth");
            var currentUser = await _context.User.FindAsync(int.Parse(userIdString));
            if (currentUser == null) return RedirectToAction("Login", "Auth");

            // Pendientes son los que tienen estado EnAutorizacion
            ViewBag.Pendientes = await _context.Document.CountAsync(d => d.CompanyId == currentUser.CompanyId && d.WorkflowState == DocumentStatus.EnAutorizacion);
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

            // Traemos los documentos de la empresa del aprobador que estén EnAutorizacion
            var docsEnAutorizacion = await _context.Document
                .Include(d => d.Iso)
                .Include(d => d.Versions)
                .Where(d => d.CompanyId == currentUser.CompanyId && d.WorkflowState == DocumentStatus.EnAutorizacion)
                .ToListAsync();

            return View(docsEnAutorizacion);
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

            if (actionType == "Aprobar") // "Aprobar" en el backend representa el "Aceptar" del aprobador
            {
                using (var transaction = await _context.Database.BeginTransactionAsync())
                {
                    try
                    {
                        // 1. Cambia estado a Aprobado
                        doc.WorkflowState = DocumentStatus.Aprobado;
                        doc.RejectionNotes = null; // Limpiamos notas de rechazo previas

                        // 2. Obtener la última versión registrada (que se está aprobando)
                        var lastVersion = doc.Versions
                            .OrderByDescending(v => v.VersionNumber)
                            .FirstOrDefault();

                        if (lastVersion != null)
                        {
                            // Brincar al siguiente entero (0.1 -> 1.0, 1.2 -> 2.0, etc.)
                            double currentVersion = lastVersion.VersionNumber;
                            double nextMajorVersion = Math.Floor(currentVersion) + 1.0;
                            lastVersion.VersionNumber = nextMajorVersion;
                            lastVersion.Status = "Vigente";
                            lastVersion.ChangeLog = "APROBADO POR AUTORIZADOR: " + (notes ?? "Aprobación final.");

                            // 3. Buscar versiones anteriores que estén como "Vigente" y marcarlas como "Obsoleto"
                            var oldVersions = await _context.DocumentVersion
                                .Where(v => v.DocumentId == id && v.Id != lastVersion.Id && v.Status == "Vigente")
                                .ToListAsync();

                            foreach (var oldVersion in oldVersions)
                            {
                                oldVersion.Status = "Obsoleto";
                            }

                            await _context.SaveChangesAsync();
                            await transaction.CommitAsync();

                            // 4. Extraer texto del PDF
                            string pdfText = "";
                            if (!string.IsNullOrEmpty(lastVersion.FileUrl))
                            {
                                var filePath = Path.Combine(_environment.WebRootPath, lastVersion.FileUrl.TrimStart('/'));
                                pdfText = PdfParser.ExtractText(filePath);
                            }

                            // 5. Sincronizar a MongoDB (directamente) y PHP (PostgreSQL)
                            await SynchronizeToExternalServices(doc, lastVersion, currentUser.CompanyId ?? 0, pdfText);
                        }
                        else
                        {
                            throw new Exception("No se encontró ninguna versión para autorizar.");
                        }
                    }
                    catch (System.Exception ex)
                    {
                        await transaction.RollbackAsync();
                        ModelState.AddModelError("", "Error al procesar la aprobación: " + ex.Message);
                        return View("Review", doc);
                    }
                }
            }
            else if (actionType == "Rechazar")
            {
                // Notas obligatorias si rechaza
                if (string.IsNullOrWhiteSpace(notes))
                {
                    ModelState.AddModelError("", "Debes escribir una nota explicando el motivo del rechazo.");
                    return View("Review", doc);
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

            return RedirectToAction(nameof(Index));
        }

        private async Task SynchronizeToExternalServices(Document doc, DocumentVersion lastVersion, int companyId, string pdfText)
        {
            try
            {
                // 1. Obtener datos del Autor
                var author = await _context.User.FindAsync(doc.AuthorId);
                var authorName = author?.Username ?? "Autor";

                // 2. Guardar en MongoDB directamente usando el C# Driver
                await _mongoService.SaveApprovedDocumentAsync(doc, lastVersion, authorName, pdfText);

                // 3. Leer archivo y convertir a Base64 para sincronizar a PHP
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

                // 4. Configurar URLs
                var phpUrl = _configuration["PhpServiceUrl"] ?? "http://web-php";

                using var client = new HttpClient();

                // 5. Enviar a PHP (PostgreSQL)
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

                // Determinar el endpoint adecuado en PHP (si es versión > 1.0, actualizará)
                var phpEndpoint = (lastVersion != null && lastVersion.VersionNumber > 1.0)
                    ? "api/actualizar.php"
                    : "api/recibir.php";

                var phpResponse = await client.PostAsJsonAsync($"{phpUrl}/{phpEndpoint}", phpPayload);
                if (!phpResponse.IsSuccessStatusCode)
                {
                    var errorContent = await phpResponse.Content.ReadAsStringAsync();
                    Console.WriteLine($"Error al sincronizar con PHP ({phpEndpoint}): {phpResponse.StatusCode} - {errorContent}");
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Excepción en la sincronización: {ex.Message}");
            }
        }
    }
}
