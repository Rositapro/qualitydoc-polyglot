using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;
using MongoDB.Bson;
using MongoDB.Driver;
using QualityDocc.Application.Interfaces;
using QualityDocc.Domain.Entities;

namespace QualityDocc.Infrastructure.Services
{
    public class MongoDocumentService : IMongoDocumentService
    {
        private readonly IMongoCollection<BsonDocument> _collection;

        public MongoDocumentService(IConfiguration configuration)
        {
            var mongoUri = configuration["MongoSettings:ConnectionString"] ?? "mongodb://db-mongodb:27017";
            var databaseName = configuration["MongoSettings:DatabaseName"] ?? "qualitydoc";
            var collectionName = configuration["MongoSettings:CollectionName"] ?? "documents";

            var client = new MongoClient(mongoUri);
            var database = client.GetDatabase(databaseName);
            _collection = database.GetCollection<BsonDocument>(collectionName);
        }

        public async Task SaveApprovedDocumentAsync(Document doc, DocumentVersion version, string authorName, string pdfText)
        {
            // Creamos el documento BSON para MongoDB
            var mongoDoc = new BsonDocument
            {
                { "title", doc.Title },
                { "fileExtension", version.Extension?.TrimStart('.') ?? "pdf" },
                { "body", pdfText },
                { "textContent", pdfText },
                { "empresaid", doc.CompanyId },
                { "status", "Vigente" }, // Estado activo
                { "createdAt", DateTime.UtcNow },
                { "updatedAt", DateTime.UtcNow },
                { "metadata", new BsonDocument
                    {
                        { "author", authorName },
                        { "version", version.VersionNumber.ToString("0.0", System.Globalization.CultureInfo.InvariantCulture) },
                        { "iso", doc.Iso?.Name ?? "ISO 9001" },
                        { "documentId", doc.Id },
                        { "versionId", version.Id }
                    }
                },
                { "tags", new BsonArray { "document", doc.Iso?.Name ?? "ISO 9001" } }
            };

            // 1. Marcar versiones anteriores en MongoDB como "Obsoleto"
            var filterObsolete = Builders<BsonDocument>.Filter.Eq("metadata.documentId", doc.Id);
            var updateObsolete = Builders<BsonDocument>.Update.Set("status", "Obsoleto");
            await _collection.UpdateManyAsync(filterObsolete, updateObsolete);

            // 2. Eliminar la versión específica si ya existía (para evitar duplicación)
            await _collection.DeleteManyAsync(Builders<BsonDocument>.Filter.Eq("metadata.versionId", version.Id));

            // 3. Insertar la nueva versión vigente
            await _collection.InsertOneAsync(mongoDoc);
        }

        public async Task<List<string>> SearchDocumentsAsync(string query, int companyId)
        {
            var results = new List<string>();

            // Filtro por empresa
            var filter = Builders<BsonDocument>.Filter.Eq("empresaid", companyId);

            if (!string.IsNullOrWhiteSpace(query))
            {
                // Búsqueda por texto (utilizando el índice de texto de MongoDB)
                var textFilter = Builders<BsonDocument>.Filter.Text(query);
                filter = Builders<BsonDocument>.Filter.And(filter, textFilter);
            }

            try
            {
                var matches = await _collection.Find(filter)
                    .Limit(20)
                    .ToListAsync();

                foreach (var doc in matches)
                {
                    results.Add(doc.ToJson());
                }
            }
            catch (Exception)
            {
                // Si falla por falta de índice de texto, hacemos una búsqueda básica con Regex sobre el título o el cuerpo
                if (!string.IsNullOrWhiteSpace(query))
                {
                    var regexFilter = Builders<BsonDocument>.Filter.Or(
                        Builders<BsonDocument>.Filter.Regex("title", new BsonRegularExpression(query, "i")),
                        Builders<BsonDocument>.Filter.Regex("body", new BsonRegularExpression(query, "i"))
                    );
                    var basicFilter = Builders<BsonDocument>.Filter.And(
                        Builders<BsonDocument>.Filter.Eq("empresaid", companyId),
                        regexFilter
                    );

                    var matches = await _collection.Find(basicFilter).Limit(20).ToListAsync();
                    foreach (var doc in matches)
                    {
                        results.Add(doc.ToJson());
                    }
                }
                else
                {
                    var matches = await _collection.Find(Builders<BsonDocument>.Filter.Eq("empresaid", companyId)).Limit(20).ToListAsync();
                    foreach (var doc in matches)
                    {
                        results.Add(doc.ToJson());
                    }
                }
            }

            return results;
        }
    }
}
