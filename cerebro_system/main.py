# main.py
import time
import json
from config import INTERVALO_AUDITORIA, ADMIN_ID
from db_client import DBClient
from ai_core import AICore
from bot_telegram import TelegramBot

# Inicializar Módulos
db = DBClient()
ai = AICore()
bot = TelegramBot()

print("🔥 CEREBRO SYSTEM 2.0 - INICIADO")
bot.enviar_mensaje("🤖 **CEREBRO ONLINE**\nEsperando pagos o comandos...")

def ciclo_auditoria():
    """Revisa y procesa pagos pendientes."""
    print("🔍 Revisando pagos pendientes...")
    pagos = db.obtener_pagos_pendientes()
    
    if not pagos:
        return

    tasa_bcv = db.obtener_tasa_dolar()
    
    for pago in pagos:
        pid = pago['id']
        rid = pago['reseller_id']
        monto = float(pago['monto'])
        ref = pago['referencia']
        img = pago['comprobante_img']
        
        if not img.startswith("http"):
            from config import BASE_URL_IMAGENES
            url_img = f"{BASE_URL_IMAGENES}{img}"
        else:
            url_img = img
            
        print(f"   -> Auditando Pago #{pid} ({monto} USD)")
        
        # 1. Chequeo Anti-Reciclaje (NUEVO)
        es_duplicado, motivo_dup = db.verificar_referencia_duplicada(ref, pid)
        if es_duplicado:
            print(f"   -> ⛔ DETECTADO RECICLAJE: {motivo_dup}")
            db.rechazar_pago(pid, f"FRAUDE: {motivo_dup}")
            bot.notificar_auditoria(pid, rid, monto, False, f"⛔ FRAUDE: {motivo_dup}")
            continue # Saltar al siguiente
            
        # 2. Análisis IA
        aprobado, razon, detalles = ai.analizar_comprobante(url_img, ref, monto, tasa_bcv)
        
        if aprobado:
            exito = db.aprobar_pago(pid, rid, monto, f"IA: {razon}")
            if exito:
                bot.notificar_auditoria(pid, rid, monto, True, razon)
            else:
                bot.enviar_mensaje(f"❌ Error DB aprobando pago #{pid}")
        else:
            db.rechazar_pago(pid, razon)
            bot.notificar_auditoria(pid, rid, monto, False, razon)

def ciclo_reportes():
    """Revisa reportes de fallo pendientes."""
    reportes = db.obtener_reportes_pendientes()
    if not reportes:
        return

    for r in reportes:
        rid = r['id']
        reseller = r['reseller_id']
        nombre_reseller = r.get('reseller_nombre', 'Desconocido')
        email_cuenta = r.get('email_cuenta', 'N/A')
        msg = r['mensaje']
        img = r['evidencia_img']
        plat = r['plataforma']
        
        # URL Imagen
        if img and not img.startswith("http"):
            from config import BASE_URL_IMAGENES
            url_img = f"{BASE_URL_IMAGENES}{img}"
        else:
            url_img = img
            
        print(f"   -> Analizando Reporte #{rid} ({plat})")
        
        # Análisis IA
        analisis = ai.analizar_reporte(url_img, msg, plat)
        
        # Notificar
        bot.notificar_reporte(rid, reseller, nombre_reseller, plat, email_cuenta, msg, analisis, url_img)
        
        # Actualizamos estado para que no salga en el loop infinito
        db.ejecutar_sql(f"UPDATE reportes_fallos SET estado = 'en_revision' WHERE id = {rid}")

def procesar_comandos(updates):
    """Procesa mensajes y botones (callbacks)."""
    for u in updates:
        
        # A. MANEJO DE BOTONES (CALLBACKS)
        if 'callback_query' in u:
            cb = u['callback_query']
            cb_id = cb['id']
            data = cb['data']
            uid = cb['from']['id']
            
            if str(uid) != str(ADMIN_ID):
                bot.responder_callback(cb_id, "⛔ No autorizado")
                continue
                
            parts = data.split('_')
            accion = parts[0]
            
            if accion == 'approve':
                # data = approve_pagoId_resellerId_monto
                pid, rid, monto = parts[1], parts[2], parts[3]
                if db.aprobar_pago(pid, rid, monto, "Aprobado MANUAL por Admin"):
                    bot.enviar_mensaje(f"✅ Pago #{pid} aprobado manualmente.")
                    bot.responder_callback(cb_id, "Aprobado")
                else:
                    bot.responder_callback(cb_id, "Error BD")
                    
            elif accion == 'reject':
                # data = reject_pagoId
                pid = parts[1]
                db.rechazar_pago(pid, "Rechazado MANUAL por Admin")
                bot.enviar_mensaje(f"❌ Pago #{pid} rechazado definitivamente.")
                bot.responder_callback(cb_id, "Rechazado")
                
            elif accion == 'fix':
                # data = fix_reporteId
                rid = parts[1]
                db.actualizar_reporte(rid, 'solucionado', 'Resuelto por soporte.')
                bot.enviar_mensaje(f"✅ Reporte #{rid} marcado como SOLUCIONADO.")
                bot.responder_callback(cb_id, "Listo")
            
            continue # Pasar al siguiente update

        # B. MANEJO DE MENSAJES DE TEXTO
        if 'message' not in u or 'text' not in u['message']:
            continue
            
        m = u['message']
        chat_id = m['chat']['id']
        texto = m.get('text', '')
        uid = m['from']['id']
        
        if str(uid) != str(ADMIN_ID):
            continue
            
        print(f"📩 Comando recibido: {texto}")
        
        # 1. Comandos SQL directos
        if texto.upper().startswith("SQL:"):
            sql = texto[4:].strip()
            res = db.ejecutar_sql(sql)
            enviar_respuesta_bd(res, chat_id)
                
        # 2. Charla General / Consultas Naturales
        else:
            respuesta = ai.chat_general(texto)
            
            # CASO A: Pedido de Datos (SQL)
            if respuesta.startswith("SQL:"):
                sql_sugerido = respuesta[4:].strip()
                bot.enviar_mensaje(f"⚙️ **Ejecutando SQL Sugerido:**\n`{sql_sugerido}`", chat_id)
                res = db.ejecutar_sql(sql_sugerido)
                enviar_respuesta_bd(res, chat_id)
            
            # CASO B: Pedido de Anuncio (Publicación)
            elif respuesta.startswith("ANUNCIO_REAL:"):
                texto_anuncio = respuesta[13:].strip()
                
                # 1. Publicar en Canal de Telegram
                bot.publicar_en_canal(texto_anuncio)
                
                # 2. Guardar en Base de Datos (Panel Notificaciones Reseller)
                # Limpiar comillas para evitar errores SQL
                msg_limpio = texto_anuncio.replace("'", "''")
                db.ejecutar_sql(f"INSERT INTO notificaciones_reseller (mensaje, fecha) VALUES ('{msg_limpio}', NOW())")
                
                bot.enviar_mensaje("🚀 **Anuncio publicado con éxito** en el canal y en el panel de socios.", chat_id)
                
            # CASO C: Charla normal
            else:
                bot.enviar_mensaje(respuesta, chat_id)

def enviar_respuesta_bd(res, chat_id):
    """Helper para formatear y enviar respuestas de BD paginadas."""
    print(f"DEBUG LOCAL - Respuesta BD: {type(res)} -> {str(res)[:200]}...")
    
    # Intento de fix JSON string
    if isinstance(res, str):
        try:
            res = json.loads(res)
        except:
            pass

    if isinstance(res, dict) and str(res.get('status')) == 'success':
        datos = res.get('datos', [])
        print(f"DEBUG LOCAL - Datos encontrados: {len(datos)} filas")
        
        if datos:
            # Encabezado simple
            msg = f"📋 {len(datos)} Resultados:\n\n"
            
            for row in datos:
                if isinstance(row, dict):
                    # Formato limpio: clave: valor
                    # Si hay pocos campos (<=3), poner en una línea. Si son más, en bloque.
                    if len(row) <= 3:
                        fila_str = " | ".join([f"{v}" for v in row.values()]) # Solo valores para brevedad
                    else:
                        fila_str = "\n".join([f"▫️ {k}: {v}" for k, v in row.items()])
                else:
                    fila_str = str(row)
                
                msg += f"{fila_str}\n\n" # Separador entre filas
            
            # PAGINACIÓN
            chunk_size = 4000
            if len(msg) > chunk_size:
                for i in range(0, len(msg), chunk_size):
                    bot.enviar_mensaje(msg[i:i+chunk_size], chat_id, parse_mode=None)
            else:
                bot.enviar_mensaje(msg, chat_id, parse_mode=None)
        else:
            bot.enviar_mensaje("✅ Consulta exitosa. Sin resultados devueltos.", chat_id)
    else:
        err_msg = res.get('message') if isinstance(res, dict) else str(res)
        print(f"DEBUG LOCAL - Error BD: {err_msg}")
        bot.enviar_mensaje(f"❌ Error en BD: {err_msg}", chat_id)

def main():
    last_check = 0
    offset = 0
    
    while True:
        ahora = time.time()
        
        if ahora - last_check > INTERVALO_AUDITORIA:
            try:
                ciclo_auditoria()
                ciclo_reportes() # <--- NUEVO: Revisar reportes también
            except Exception as e:
                print(f"Error ciclo auditoría/reportes: {e}")
            last_check = ahora
            
        try:
            updates = bot.obtener_actualizaciones(offset)
            if updates:
                procesar_comandos(updates)
                offset = updates[-1]['update_id'] + 1
        except Exception as e:
            print(f"Error polling Telegram: {e}")
            
        time.sleep(1)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n🛑 Sistema detenido.")
