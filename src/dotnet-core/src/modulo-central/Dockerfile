# Fase de compilación
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /src

# Copiar el archivo de solución (asegúrate de que este sea el nombre real de tu .slnx o .sln)
COPY ["QualityDocc.slnx", "./"]

# Copiar proyectos (ajustado con el nombre correcto "QualityDocc")
COPY ["QualityDocc.Application/QualityDocc.Application.csproj", "QualityDocc.Application/"]
COPY ["QualityDocc.Domain/QualityDocc.Domain.csproj", "QualityDocc.Domain/"]
COPY ["QualityDocc.Infrastructure/QualityDocc.Infrastructure.csproj", "QualityDocc.Infrastructure/"]
COPY ["QualityDocc.MVC/QualityDocc.MVC.csproj", "QualityDocc.MVC/"]

# Restaurar dependencias
RUN dotnet restore "QualityDocc.MVC/QualityDocc.MVC.csproj"

# Copiar todo el código fuente
COPY . .

# Compilar y publicar
RUN dotnet publish "QualityDocc.MVC/QualityDocc.MVC.csproj" -c Release -o /app/publish

# Fase de ejecución
FROM mcr.microsoft.com/dotnet/aspnet:10.0
WORKDIR /app
COPY --from=build /app/publish .

# Ejecutar la aplicación
ENTRYPOINT ["dotnet", "QualityDocc.MVC.dll"]