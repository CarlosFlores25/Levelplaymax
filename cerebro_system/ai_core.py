# ai_core.py
import requests
import base64
import json
import re
from config import URL_GEMINI_VISION, URL_GEMINI_TEXT

class AICore:
    def __init__(self):
        self.headers = {'Content-Type': 'application/json'}

    def _descargar_imagen_b64(self, url):
        """Descarga una imagen y la convierte a Base64."""
        try:
            r = requests.get(url, timeout=15)
            if r.status_code == 200:
                return base64.b64encode(r.content).decode('utf-8')
            return None
        except:
            return None

    def analizar_comprobante(self, url_imagen, referencia_esperada, monto_esperado_usd, tasa_bcv):
        """
        Analiza un comprobante de pago.
        Retorna: (Aprobado: bool, Motivo: str, DatosLeidos: dict)
        """
        img_b64 = self._descargar_imagen_b64(url_imagen)
        if not img_b64:
            return False, "No se pudo descargar la imagen del comprobante", {}

        monto_bs = monto_esperado_usd * tasa_bcv
        
        prompt = f"""
        Actúa como un auditor financiero riguroso. Analiza esta imagen de un comprobante de pago bancario.
        
        CUENTAS OFICIALES DESTINO (Deben coincidir en la imagen):
        - Binance / Zinli: Carloscruch@gmail.com
        - Pago Móvil: 0102 (Venezuela) | CI: 29911214 | Telf: 04123368325
        
        DATOS ESPERADOS (Del reporte del reseller):
        - Referencia/Seq: {referencia_esperada} (Busca coincidencia parcial o total de dígitos)
        - Monto: {monto_esperado_usd} USD  O  {monto_bs} VES (Bs)
        
        TAREA DE AUDITORÍA:
        1. Extrae el número de referencia visible.
        2. Extrae el monto total pagado.
        3. VERIFICA EL DESTINATARIO: ¿El dinero fue enviado a una de nuestras Cuentas Oficiales?
           - Si ves un correo o teléfono distinto, RECHAZA inmediatamente (Posible estafa).
        4. Compara con los datos esperados.
        
        Responde ESTRICTAMENTE este JSON (sin markdown):
        {{
            "referencia_encontrada": "texto_hallado",
            "coincide_referencia": true/false,
            "monto_leido": 0.00,
            "moneda": "USD/VES",
            "coincide_monto": true/false,
            "destinatario_correcto": true/false,
            "conclusion": "APROBAR" o "RECHAZAR",
            "razon": "Explicación corta (Ej: Monto incorrecto / Destinatario ajeno / Ilegible)"
        }}
        """

        payload = {
            "contents": [{
                "parts": [
                    {"text": prompt},
                    {"inline_data": {"mime_type": "image/jpeg", "data": img_b64}}
                ]
            }],
            "generationConfig": {"temperature": 0.1, "maxOutputTokens": 500}
        }

        try:
            resp = requests.post(URL_GEMINI_VISION, headers=self.headers, json=payload, timeout=45)
            if resp.status_code != 200:
                return False, f"Error API IA: {resp.status_code}", {}
            
            # Parsear respuesta
            data = resp.json()
            raw_text = data['candidates'][0]['content']['parts'][0]['text']
            
            # Limpiar JSON (quitar ```json ... ```)
            json_str = re.sub(r"```json|```", "", raw_text).strip()
            resultado = json.loads(json_str)
            
            es_valido = resultado.get("conclusion") == "APROBAR"
            razon = resultado.get("razon", "Sin razón especificada")
            
            return es_valido, razon, resultado

        except Exception as e:
            return False, f"Error procesando IA: {str(e)}", {}

    def analizar_reporte(self, url_imagen, descripcion_usuario, plataforma):
        """
        Analiza un reporte de fallo técnico.
        """
        img_b64 = self._descargar_imagen_b64(url_imagen)
        if not img_b64:
            return "No se pudo ver la imagen.", "Revisar conexión"

        prompt = f"""
        Actúa como soporte técnico nivel 2. Analiza esta captura de pantalla enviada por un usuario reportando un fallo en {plataforma}.
        
        QUEJA DEL USUARIO: "{descripcion_usuario}"
        
        TAREA:
        1. Lee el mensaje de error en la pantalla (si hay).
        2. Determina qué está pasando (¿Clave incorrecta? ¿Membresía pausada? ¿Geo-bloqueo?).
        3. Genera un resumen técnico corto para el administrador.
        
        Responde SOLO el resumen técnico. Sé breve. Ej: "Error de password incorrecto. Se sugiere resetear clave."
        """

        payload = {
            "contents": [{
                "parts": [
                    {"text": prompt},
                    {"inline_data": {"mime_type": "image/jpeg", "data": img_b64}}
                ]
            }],
            "generationConfig": {"temperature": 0.2, "maxOutputTokens": 1000}
        }

        try:
            resp = requests.post(URL_GEMINI_VISION, headers=self.headers, json=payload, timeout=45)
            if resp.status_code == 200:
                data = resp.json()
                # Verificar estructura antes de acceder
                try:
                    if 'candidates' in data and data['candidates']:
                        candidate = data['candidates'][0]
                        # Verificar si fue bloqueado por seguridad
                        if candidate.get('finishReason') == 'SAFETY':
                            return "IA: Bloqueado por filtro de seguridad (contenido sensible detectado)."
                        
                        if 'content' in candidate and 'parts' in candidate['content']:
                            return candidate['content']['parts'][0]['text']
                        else:
                            print(f"DEBUG IA RAW RESPONSE: {json.dumps(data)}") # Ver qué devolvió realmente
                            return "IA: Respuesta sin contenido de texto."
                    else:
                        print(f"DEBUG IA RAW RESPONSE: {json.dumps(data)}")
                        return "IA: Respuesta vacía de Google."
                except Exception as parse_error:
                    print(f"DEBUG IA EXCEPTION: {parse_error} | DATA: {json.dumps(data)}")
                    return "IA: Error procesando respuesta."
            
            return f"Error API IA ({resp.status_code}): {resp.text[:50]}..."
        except Exception as e:
            return f"Error interno IA: {str(e)}"

    def chat_general(self, mensaje_usuario, contexto_bd=""):
        """Chat general para consultas SQL o texto."""
        
        # Cargar esquema desde JSON
        esquema_str = ""
        try:
            with open("cerebro_system/db_schema.json", "r", encoding="utf-8") as f:
                esquema_json = json.load(f)
                esquema_str = json.dumps(esquema_json, indent=2, ensure_ascii=False)
        except Exception as e:
            esquema_str = "Error cargando esquema: " + str(e)

        prompt = f"""
        Eres GHOST, el asistente de administración de Level Play Max.
        
        TU CONOCIMIENTO (ESQUEMA BASE DE DATOS EXACTO):
        {esquema_str}
        
        INSTRUCCIONES CLAVE:
        1. Para "STOCK" o "DISPONIBILIDAD":
           - Cuenta perfiles LIBRES: WHERE reseller_id IS NULL AND cliente_id IS NULL.
           - Agrupa por 'c.plataforma' (tabla cuentas).
           - SQL Ejemplo: SELECT c.plataforma, COUNT(p.id) as cantidad FROM perfiles p JOIN cuentas c ON p.cuenta_id=c.id WHERE p.reseller_id IS NULL GROUP BY c.plataforma;
           
        2. Para "VENTAS":
           - Suma 'precio_usd' de tabla 'pedidos' (estado='aprobado') O suma 'monto' de 'movimientos_reseller' (tipo='compra').
        
        CONTEXTO ACTUAL: {contexto_bd}
        USUARIO DICE: "{mensaje_usuario}"
        
        DIRECTRICES DE PERSONALIDAD:
        - Eres "Ghost", el cerebro digital de Level Play Max.
        - Tu tono es profesional, eficiente pero amable y con un toque tecnológico.
        - Si el usuario saluda o charla, responde con naturalidad y cortesía.
        
        REGLAS DE EJECUCIÓN:
        1. Si el usuario pide DATOS, LISTAS o MODIFICAR la BD:
           - Genera ÚNICAMENTE el código SQL, empezando por 'SQL: '.
           - No añadas saludos ni explicaciones en este caso, solo el código.
        
        2. Si el usuario pide ANUNCIAR, AVISAR o INFORMAR algo al canal/grupo:
           - Genera el texto del anuncio empezando por 'ANUNCIO_REAL: '.
           - Ejemplo: "ANUNCIO_REAL: 🚀 ¡Nuevas cuentas de Netflix disponibles! Aprovecha ahora."
           
        3. Si el usuario pide ayuda o conversa:
           - Responde como un asistente inteligente.
        """
        
        payload = {
            "contents": [{"parts": [{"text": prompt}]}]
        }
        
        try:
            resp = requests.post(URL_GEMINI_TEXT, headers=self.headers, json=payload, timeout=20)
            if resp.status_code == 200:
                return resp.json()['candidates'][0]['content']['parts'][0]['text']
            else:
                print(f"❌ Error IA HTTP {resp.status_code}: {resp.text}")
                return f"Error IA: {resp.status_code}"
        except Exception as e:
            print(f"❌ Excepción IA: {e}")
            return f"Error interno IA: {str(e)}"
        return "Error desconocido con el cerebro."
