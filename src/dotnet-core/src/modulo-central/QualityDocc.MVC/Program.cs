using Microsoft.AspNetCore.Authentication.Cookies; // 👈 Añadido para el manejo de sesiones
using Microsoft.EntityFrameworkCore;
using QualityDocc.Application.Interfaces;
using QualityDocc.Application.Services;

var builder = WebApplication.CreateBuilder(args);

// 1. Registrar el contexto de la base de datos (DbContext)
builder.Services.AddDbContext<QualityDocc.Infrastructure.Data.ApplicationDbContext>(options =>
    options.UseSqlServer(builder.Configuration.GetConnectionString("DefaultConnection"),
        b => b.MigrationsAssembly("QualityDocc.Infrastructure")));

// Mapear la clase base DbContext genérica al contexto real de tu infraestructura
builder.Services.AddScoped<DbContext>(provider =>
    provider.GetRequiredService<QualityDocc.Infrastructure.Data.ApplicationDbContext>());

// 2. CONFIGURAR EL ESQUEMA DE AUTENTICACIÓN POR COOKIES
builder.Services.AddAuthentication(CookieAuthenticationDefaults.AuthenticationScheme)
    .AddCookie(options =>
    {
        options.LoginPath = "/Auth/Login"; // Si un usuario sin registrar intenta saltarse al inicio, lo bota aquí
        options.ExpireTimeSpan = TimeSpan.FromMinutes(20); // Duración de la sesión activa
    });

// Add services to the container.
builder.Services.AddControllersWithViews();

// 3. REGISTRAR EL SERVICIO CORE DE VERSIONES DE QUALITYDOC
builder.Services.AddScoped<IDocumentService, DocumentService>();

var app = builder.Build();

// Configure the HTTP request pipeline.
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Home/Error");
    // The default HSTS value is 30 days. You may want to change this for production scenarios, see https://aka.ms/aspnetcore-hsts.
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseStaticFiles();
app.UseRouting();

// 🚨 IMPORTANTE: El orden de estos dos middlewares es vital para que no falle
app.UseAuthentication(); // 👈 Añadido: Comprueba "quién" es el usuario (gafete de entrada)
app.UseAuthorization();  // Comprueba a "qué" tiene permiso de entrar

app.MapStaticAssets();

app.MapControllerRoute(
    name: "default",
    pattern: "{controller=Auth}/{action=Login}/{id?}");

app.Run();