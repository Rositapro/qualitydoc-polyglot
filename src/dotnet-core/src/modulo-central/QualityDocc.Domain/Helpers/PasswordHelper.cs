using System;
using System.Security.Cryptography;

namespace QualityDocc.Domain.Helpers
{
    public static class PasswordHelper
    {
        private const int SaltSize = 16; // 128 bit
        private const int KeySize = 32;  // 256 bit
        private const int Iterations = 10000;

        public static string HashPassword(string password)
        {
            using (var algorithm = new Rfc2898DeriveBytes(password, SaltSize, Iterations, HashAlgorithmName.SHA256))
            {
                var key = algorithm.GetBytes(KeySize);
                var salt = algorithm.Salt;

                var hashBytes = new byte[SaltSize + KeySize];
                Array.Copy(salt, 0, hashBytes, 0, SaltSize);
                Array.Copy(key, 0, hashBytes, SaltSize, KeySize);

                return Convert.ToBase64String(hashBytes);
            }
        }

        public static bool VerifyPassword(string password, string hashedPassword)
        {
            try
            {
                if (string.IsNullOrEmpty(hashedPassword) || string.IsNullOrEmpty(password))
                {
                    return false;
                }

                var hashBytes = Convert.FromBase64String(hashedPassword);
                if (hashBytes.Length != SaltSize + KeySize)
                {
                    return false;
                }

                var salt = new byte[SaltSize];
                Array.Copy(hashBytes, 0, salt, 0, SaltSize);

                var key = new byte[KeySize];
                Array.Copy(hashBytes, SaltSize, key, 0, KeySize);

                using (var algorithm = new Rfc2898DeriveBytes(password, salt, Iterations, HashAlgorithmName.SHA256))
                {
                    var keyToCheck = algorithm.GetBytes(KeySize);
                    
                    // Comparación en tiempo constante para evitar ataques de temporización
                    int diff = 0;
                    for (int i = 0; i < KeySize; i++)
                    {
                        diff |= key[i] ^ keyToCheck[i];
                    }
                    return diff == 0;
                }
            }
            catch
            {
                return false;
            }
        }
    }
}
