#!/bin/bash
set -e

# Configuración
BASE_URL="http://admin-dotnet:8080"
COOKIE_FILE="/tmp/cookies.txt"
SAMPLE_PDF="/var/www/html/uploads/QD-1003_v1.0_1780932158.pdf"
DOC_ID=${1:-2005}

echo "============================================="
echo "INICIANDO PRUEBA DE CORRECCIÓN (ID=$DOC_ID)"
echo "============================================="

# 1. Login como Autor
echo -n "1. Iniciando sesión como Autor (Rosalinda)... "
curl -s -c "$COOKIE_FILE" \
  -d "email=rosalindacedillo2017@gmail.com" \
  -d "password=12345" \
  "$BASE_URL/Auth/Login" > /dev/null
echo "¡ÉXITO!"

# 2. Subir Corrección (Se asocia a DOC_ID)
echo -n "2. Subiendo corrección (ID=$DOC_ID) como Autor... "
upload_resp=$(curl -s -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
  -F "Id=$DOC_ID" \
  -F "Title=Politica de Seguridad ISO 27001 - Rev 2026" \
  -F "Description=Manual corporativo de seguridad de la informacion con politicas corregidas." \
  -F "IsoId=1" \
  -F "VersionNumber=1.1" \
  -F "ChangeLog=Correccion de erratas menores y enlaces rotos." \
  -F "action=submit" \
  -F "archivo=@$SAMPLE_PDF" \
  -w "%{http_code}" \
  "$BASE_URL/Author/Upload")

if [ "$upload_resp" -eq 302 ] || [ "$upload_resp" -eq 200 ]; then
  echo "¡ÉXITO! (Respuesta: $upload_resp)"
else
  echo "¡FALLÓ! (Respuesta: $upload_resp)"
  exit 1
fi

# Limpiar cookies y simular cambio de rol
rm -f "$COOKIE_FILE"

# 3. Login como Revisor
echo -n "3. Iniciando sesión como Revisor (Odeth)... "
curl -s -c "$COOKIE_FILE" \
  -d "email=odeth@gmail.com" \
  -d "password=12345" \
  "$BASE_URL/Auth/Login" > /dev/null
echo "¡ÉXITO!"

# 4. Revisor envía al Autorizador
echo -n "4. Revisor envía corrección ID=$DOC_ID al Autorizador... "
review_resp=$(curl -s -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
  -d "id=$DOC_ID" \
  -d "actionType=Aprobar" \
  -d "notes=Cambios menores revisados." \
  -w "%{http_code}" \
  "$BASE_URL/Reviewer/ProcessReview")

if [ "$review_resp" -eq 302 ] || [ "$review_resp" -eq 200 ]; then
  echo "¡ÉXITO! (Respuesta: $review_resp)"
else
  echo "¡FALLÓ! (Respuesta: $review_resp)"
  exit 1
fi

# Limpiar cookies y simular cambio de rol
rm -f "$COOKIE_FILE"

# 5. Login como Aprobador
echo -n "5. Iniciando sesión como Aprobador (aprobador)... "
curl -s -c "$COOKIE_FILE" \
  -d "email=aprobador@qualitydoc.com" \
  -d "password=Document2026!" \
  "$BASE_URL/Auth/Login" > /dev/null
echo "¡ÉXITO!"

# 6. Aprobador aprueba el documento (Aceptar)
echo -n "6. Aprobador acepta y autoriza corrección ID=$DOC_ID... "
approve_resp=$(curl -s -b "$COOKIE_FILE" -c "$COOKIE_FILE" \
  -d "id=$DOC_ID" \
  -d "actionType=Aprobar" \
  -d "notes=Publicacion de la revision 2026." \
  -w "%{http_code}" \
  "$BASE_URL/Approver/ProcessReview")

if [ "$approve_resp" -eq 302 ] || [ "$approve_resp" -eq 200 ]; then
  echo "¡ÉXITO! (Respuesta: $approve_resp)"
else
  echo "¡FALLÓ! (Respuesta: $approve_resp)"
  exit 1
fi

echo "============================================="
echo "¡FLUJO DE CORRECCIÓN COMPLETADO CON ÉXITO!"
echo "============================================="
