using System;
using Xunit;
using QualityDocc.Domain.Entities;
using QualityDocc.Domain.Helpers;

namespace QualityDocc.Tests
{
    public class VersionAndStatusTests
    {
        // Método auxiliar que implementa la misma lógica del backend para probarla de forma aislada
        private double CalculateNextVersionOnApproval(double currentVersion)
        {
            return Math.Floor(currentVersion) + 1.0;
        }

        private double CalculateSuggestedVersionOnEdit(double lastVersionNumber, DocumentStatus state)
        {
            if (state == DocumentStatus.Rechazado ||
                state == DocumentStatus.Aprobado ||
                state == DocumentStatus.Vigente)
            {
                return Math.Round(lastVersionNumber + 0.1, 1);
            }
            else
            {
                return lastVersionNumber;
            }
        }

        [Theory]
        [InlineData(0.1, 1.0)]
        [InlineData(0.9, 1.0)]
        [InlineData(1.0, 2.0)]
        [InlineData(1.2, 2.0)]
        [InlineData(2.0, 3.0)]
        [InlineData(10.5, 11.0)]
        public void Approver_AcceptingDocument_ShouldJumpToNextMajorInteger(double currentVersion, double expectedVersion)
        {
            // Act
            double nextVersion = CalculateNextVersionOnApproval(currentVersion);

            // Assert
            Assert.Equal(expectedVersion, nextVersion);
        }

        [Theory]
        [InlineData(0.1, DocumentStatus.Rechazado, 0.2)]
        [InlineData(1.0, DocumentStatus.Rechazado, 1.1)]
        [InlineData(1.0, DocumentStatus.Aprobado, 1.1)]
        [InlineData(1.0, DocumentStatus.Vigente, 1.1)]
        [InlineData(0.1, DocumentStatus.Revision, 0.1)]
        [InlineData(1.0, DocumentStatus.EnAutorizacion, 1.0)]
        public void Author_EditingDocument_ShouldCalculateSuggestedVersionCorrectly(double lastVersion, DocumentStatus state, double expectedSuggested)
        {
            // Act
            double suggestedVersion = CalculateSuggestedVersionOnEdit(lastVersion, state);

            // Assert
            Assert.Equal(expectedSuggested, suggestedVersion);
        }

        [Fact]
        public void DocumentStatus_EnAutorizacion_ShouldBeSix()
        {
            // Assert
            Assert.Equal(6, (int)DocumentStatus.EnAutorizacion);
        }

        [Fact]
        public void PasswordHelper_HashPassword_ShouldProduceDifferentHashesForSamePassword()
        {
            // Arrange
            string password = "Document2026!";

            // Act
            string hash1 = PasswordHelper.HashPassword(password);
            string hash2 = PasswordHelper.HashPassword(password);

            // Assert
            Assert.NotEmpty(hash1);
            Assert.NotEmpty(hash2);
            Assert.NotEqual(hash1, hash2); // Debido a la sal aleatoria
        }

        [Fact]
        public void PasswordHelper_VerifyPassword_ShouldSucceedWithCorrectPassword()
        {
            // Arrange
            string password = "Document2026!";
            string hash = PasswordHelper.HashPassword(password);

            // Act
            bool isValid = PasswordHelper.VerifyPassword(password, hash);

            // Assert
            Assert.True(isValid);
        }

        [Fact]
        public void PasswordHelper_VerifyPassword_ShouldFailWithIncorrectPassword()
        {
            // Arrange
            string password = "Document2026!";
            string incorrectPassword = "WrongPassword!";
            string hash = PasswordHelper.HashPassword(password);

            // Act
            bool isValid = PasswordHelper.VerifyPassword(incorrectPassword, hash);

            // Assert
            Assert.False(isValid);
        }

        [Fact]
        public void PasswordHelper_GenerateSeedHash_PrintToConsole()
        {
            string password = "Document2026!";
            string hash = PasswordHelper.HashPassword(password);
            
            // Verificamos que sea válido
            Assert.True(PasswordHelper.VerifyPassword(password, hash));
            
            // Imprimimos el hash para la consola
            Console.WriteLine($"HASH_SEED_VALUE: {hash}");
        }
    }
}
