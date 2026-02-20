# bot_telegram.py
import requests
import time
import json
from config import TOKEN_TELEGRAM, ADMIN_ID

class TelegramBot:
    def __init__(self):
        self.base_url = f"https://api.telegram.org/bot{TOKEN_TELEGRAM}"
        self.admin_id = ADMIN_ID

    def enviar_mensaje(self, texto, chat_id=None, parse_mode="Markdown", reply_markup=None):
        """Envía un mensaje a un chat específico (o al admin por defecto)."""
        if not chat_id:
            chat_id = self.admin_id
            
        url = f"{self.base_url}/sendMessage"
        payload = {
            "chat_id": chat_id,
            "text": texto
        }
        if parse_mode:
            payload["parse_mode"] = parse_mode
        if reply_markup:
            payload["reply_markup"] = reply_markup

        try:
            resp = requests.post(url, json=payload, timeout=10)
            if resp.status_code != 200:
                print(f"❌ Error Telegram {resp.status_code}: {resp.text}")
        except Exception as e:
            print(f"❌ Excepción enviando Telegram: {e}")

    def obtener_actualizaciones(self, offset=None):
        """Obtiene nuevos mensajes (Polling)."""
        url = f"{self.base_url}/getUpdates"
        params = {"timeout": 30, "offset": offset}
        try:
            resp = requests.get(url, params=params, timeout=40)
            if resp.status_code == 200:
                return resp.json().get("result", [])
        except:
            pass
        return []

    def notificar_auditoria(self, pago_id, reseller_id, monto, estado, razon):
        """Formatea y envía el reporte de auditoría con BOTONES."""
        emoji = "✅" if estado else "⚠️"
        titulo = "PAGO APROBADO" if estado else "REVISIÓN MANUAL"
        
        msg = f"""
{emoji} *{titulo}*
🆔 Pago ID: `{pago_id}`
👤 Reseller ID: `{reseller_id}`
💰 Monto: `${monto}`
📝 Nota: {razon}
"""
        # Si requiere revisión, añadimos botones para actuar rápido
        teclado = None
        if not estado:
            teclado = {
                "inline_keyboard": [
                    [
                        {"text": "✅ Forzar Aprobación", "callback_data": f"approve_{pago_id}_{reseller_id}_{monto}"},
                        {"text": "❌ Rechazar Definitivo", "callback_data": f"reject_{pago_id}"}
                    ]
                ]
            }
            
        self.enviar_mensaje(msg, reply_markup=teclado)

    def notificar_reporte(self, reporte_id, reseller_id, plataforma, queja, analisis_ia, url_imagen=None):
        """Envía alerta de reporte con foto y análisis."""
        caption = f"""
🛠️ *REPORTE DE FALLO*
🆔 ID: `{reporte_id}` | 👤 Reseller: `{reseller_id}`
📺 Plat: {plataforma}

🗣️ *Dice:* {queja}
🤖 *IA:* {analisis_ia}
"""
        # Botones de acción rápida
        teclado = {
            "inline_keyboard": [
                [
                    {"text": "✅ Solucionado", "callback_data": f"fix_{reporte_id}"},
                    {"text": "💬 Responder", "callback_data": f"reply_{reporte_id}"}
                ]
            ]
        }

        # Intentar enviar con foto
        if url_imagen and url_imagen.startswith("http"):
            url_api = f"{self.base_url}/sendPhoto"
            payload = {
                "chat_id": self.admin_id,
                "photo": url_imagen,
                "caption": caption,
                "parse_mode": "Markdown",
                "reply_markup": json.dumps(teclado)
            }
            try:
                requests.post(url_api, json=payload, timeout=15)
                return
            except:
                pass # Si falla foto, enviamos texto plano

        # Fallback texto
        self.enviar_mensaje(caption, reply_markup=teclado)

    def responder_callback(self, callback_id, texto):
        """Cierra el relojito de carga del botón."""
        url = f"{self.base_url}/answerCallbackQuery"
        try:
            requests.post(url, json={"callback_query_id": callback_id, "text": texto}, timeout=5)
        except:
            pass

    def publicar_en_canal(self, texto):
        """Publica un mensaje en el canal oficial."""
        from config import CHANNEL_ID
        self.enviar_mensaje(texto, chat_id=CHANNEL_ID)
