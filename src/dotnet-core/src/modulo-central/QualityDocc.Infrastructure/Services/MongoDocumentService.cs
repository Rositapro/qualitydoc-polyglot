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
            Console.WriteLine($"SearchDocumentsAsync CALL: query='{query}', companyId={companyId}");
            var results = new List<string>();

            // Filtro por empresa y por estado vigente
            var filter = Builders<BsonDocument>.Filter.And(
                Builders<BsonDocument>.Filter.Eq("empresaid", companyId),
                Builders<BsonDocument>.Filter.Eq("status", "Vigente")
            );

            if (!string.IsNullOrWhiteSpace(query))
            {
                // Búsqueda por texto (utilizando el índice de texto de MongoDB)
                var textFilter = Builders<BsonDocument>.Filter.Text(query);
                filter = Builders<BsonDocument>.Filter.And(filter, textFilter);
            }

            try
            {
                Console.WriteLine("Executing primary Find query on MongoDB...");
                var matches = await _collection.Find(filter)
                    .Limit(20)
                    .ToListAsync();
                Console.WriteLine($"Primary query results matches count: {matches.Count}");

                // Fallback: Si no hay resultados de búsqueda por texto pero hay consulta, probamos búsqueda con Regex (para sub-cadenas concatenadas)
                if (matches.Count == 0 && !string.IsNullOrWhiteSpace(query))
                {
                    Console.WriteLine("Matches are 0, falling back to Regex query...");
                    var regexFilter = Builders<BsonDocument>.Filter.Or(
                        Builders<BsonDocument>.Filter.Regex("title", new BsonRegularExpression(query, "i")),
                        Builders<BsonDocument>.Filter.Regex("textContent", new BsonRegularExpression(query, "i")),
                        Builders<BsonDocument>.Filter.Regex("body", new BsonRegularExpression(query, "i"))
                    );
                    var basicFilter = Builders<BsonDocument>.Filter.And(
                        Builders<BsonDocument>.Filter.Eq("empresaid", companyId),
                        Builders<BsonDocument>.Filter.Eq("status", "Vigente"),
                        regexFilter
                    );
                    matches = await _collection.Find(basicFilter).Limit(20).ToListAsync();
                    Console.WriteLine($"Regex query results matches count: {matches.Count}");
                }

                foreach (var doc in matches)
                {
                    results.Add(doc.ToJson(new MongoDB.Bson.IO.JsonWriterSettings { OutputMode = MongoDB.Bson.IO.JsonOutputMode.RelaxedExtendedJson }));
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"SearchDocumentsAsync EXCEPTION: {ex.Message} | StackTrace: {ex.StackTrace}");
                // Si falla por falta de índice de texto, hacemos una búsqueda básica con Regex sobre el título o el cuerpo
                if (!string.IsNullOrWhiteSpace(query))
                {
                    var regexFilter = Builders<BsonDocument>.Filter.Or(
                        Builders<BsonDocument>.Filter.Regex("title", new BsonRegularExpression(query, "i")),
                        Builders<BsonDocument>.Filter.Regex("textContent", new BsonRegularExpression(query, "i")),
                        Builders<BsonDocument>.Filter.Regex("body", new BsonRegularExpression(query, "i"))
                    );
                    var basicFilter = Builders<BsonDocument>.Filter.And(
                        Builders<BsonDocument>.Filter.Eq("empresaid", companyId),
                        Builders<BsonDocument>.Filter.Eq("status", "Vigente"),
                        regexFilter
                    );

                    var matches = await _collection.Find(basicFilter).Limit(20).ToListAsync();
                    Console.WriteLine($"Catch Regex query results matches count: {matches.Count}");
                    foreach (var doc in matches)
                    {
                        results.Add(doc.ToJson(new MongoDB.Bson.IO.JsonWriterSettings { OutputMode = MongoDB.Bson.IO.JsonOutputMode.RelaxedExtendedJson }));
                    }
                }
                else
                {
                    var basicFilter = Builders<BsonDocument>.Filter.And(
                        Builders<BsonDocument>.Filter.Eq("empresaid", companyId),
                        Builders<BsonDocument>.Filter.Eq("status", "Vigente")
                    );
                    var matches = await _collection.Find(basicFilter).Limit(20).ToListAsync();
                    Console.WriteLine($"Catch empty query results matches count: {matches.Count}");
                    foreach (var doc in matches)
                    {
                        results.Add(doc.ToJson(new MongoDB.Bson.IO.JsonWriterSettings { OutputMode = MongoDB.Bson.IO.JsonOutputMode.RelaxedExtendedJson }));
                    }
                }
            }

            return results;
        }

        public async Task<List<string>> GetObsoleteVersionsAsync(int documentId, int companyId)
        {
            var results = new List<string>();
            try
            {
                var filter = Builders<BsonDocument>.Filter.And(
                    Builders<BsonDocument>.Filter.Eq("empresaid", companyId),
                    Builders<BsonDocument>.Filter.Eq("metadata.documentId", documentId),
                    Builders<BsonDocument>.Filter.Eq("status", "Obsoleto")
                );

                var matches = await _collection.Find(filter)
                    .Sort(Builders<BsonDocument>.Sort.Descending("metadata.version"))
                    .ToListAsync();

                foreach (var doc in matches)
                {
                    results.Add(doc.ToJson(new MongoDB.Bson.IO.JsonWriterSettings { OutputMode = MongoDB.Bson.IO.JsonOutputMode.RelaxedExtendedJson }));
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"GetObsoleteVersionsAsync EXCEPTION: {ex.Message}");
            }

            return results;
        }
    }
}
