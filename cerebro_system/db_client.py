# db_client.py
import requests
import json
from config import URL_API_DB, TOKEN_SECRETO_DB

class DBClient:
    def __init__(self):
        self.url = URL_API_DB
        self.token = TOKEN_SECRETO_DB
        self.session = requests.Session()

    def ejecutar_sql(self, sql):
        """Envía una consulta SQL cruda al backend PHP con FILTRO DE SEGURIDAD."""
        try:
            # Limpieza básica
            sql = sql.replace("```sql", "").replace("```", "").strip()
            sql_upper = sql.upper()
            
            # --- FILTRO ANTI-CATÁSTROFES ---
            prohibidos = ["DROP TABLE", "TRUNCATE TABLE", "DROP DATABASE"]
            for p in prohibidos:
                if p in sql_upper:
                    return {"status": "error", "message": f"⛔ BLOQUEADO: Comando destructivo '{p}' detectado."}
            
            # Bloquear DELETE/UPDATE masivos sin WHERE
            if ("DELETE FROM" in sql_upper or "UPDATE" in sql_upper) and "WHERE" not in sql_upper:
                 return {"status": "error", "message": "⛔ BLOQUEADO: DELETE/UPDATE sin cláusula WHERE (Riesgo masivo)."}
            # -------------------------------
            
            payload = {
                'token': self.token,
                'sql': sql
            }
            
            response = self.session.post(self.url, data=payload, timeout=20)
            
            if response.status_code != 200:
                return {"status": "error", "message": f"Error HTTP {response.status_code}"}
                
            try:
                return response.json()
            except json.JSONDecodeError:
                return {"status": "error", "message": "Respuesta inválida del servidor (No JSON)", "raw": response.text}
                
        except Exception as e:
            return {"status": "error", "message": str(e)}

    def obtener_pagos_pendientes(self):
        """Busca recargas que necesitan auditoría."""
        sql = "SELECT id, reseller_id, monto, metodo, referencia, comprobante_img FROM historial_recargas WHERE estado = 'pendiente' LIMIT 5"
        res = self.ejecutar_sql(sql)
        if res.get('status') == 'success':
            return res.get('datos', [])
        return []

    def verificar_referencia_duplicada(self, referencia, pago_id_actual):
        """Revisa si la referencia ya fue usada en otro pago exitoso."""
        # Buscar en historial_recargas donde referencia sea igual, id diferente y estado aprobado
        sql = f"SELECT id, reseller_id, fecha FROM historial_recargas WHERE referencia = '{referencia}' AND id != {pago_id_actual} AND estado = 'aprobado' LIMIT 1"
        res = self.ejecutar_sql(sql)
        
        if res.get('status') == 'success' and res.get('datos'):
            # Encontró un duplicado
            duplicado = res['datos'][0]
            return True, f"Referencia usada antes en Pago #{duplicado['id']} (Reseller {duplicado['reseller_id']})"
            
        return False, "Ok"

    def obtener_reportes_pendientes(self):
        """Busca reportes de fallo pendientes con datos enriquecidos."""
        sql = """
        SELECT 
            r.id, 
            r.reseller_id, 
            d.nombre as reseller_nombre,
            r.mensaje, 
            r.evidencia_img, 
            c.plataforma,
            c.email_cuenta
        FROM reportes_fallos r 
        LEFT JOIN distribuidores d ON r.reseller_id = d.id
        LEFT JOIN perfiles p ON r.perfil_id = p.id 
        LEFT JOIN cuentas c ON p.cuenta_id = c.id 
        WHERE r.estado = 'pendiente' 
        LIMIT 3
        """
        res = self.ejecutar_sql(sql)
        if res.get('status') == 'success':
            return res.get('datos', [])
        return []

    def actualizar_reporte(self, reporte_id, estado, respuesta_admin):
        """Actualiza el estado de un reporte."""
        sql = f"UPDATE reportes_fallos SET estado = '{estado}', respuesta_admin = '{respuesta_admin}' WHERE id = {reporte_id}"
        return self.ejecutar_sql(sql)

    def aprobar_pago(self, pago_id, reseller_id, monto, nota="Aprobado por IA"):
        """
        Transacción para aprobar pago enviando consultas secuenciales.
        """
        # 1. Actualizar Historial
        sql1 = f"UPDATE historial_recargas SET estado = 'aprobado', nota = '{nota}' WHERE id = {pago_id}"
        res1 = self.ejecutar_sql(sql1)
        
        if res1.get('status') == 'success':
            # 2. Sumar Saldo
            sql2 = f"UPDATE distribuidores SET saldo = saldo + {monto} WHERE id = {reseller_id}"
            self.ejecutar_sql(sql2)
            
            # 3. Registrar Movimiento
            sql3 = f"INSERT INTO movimientos_reseller (reseller_id, tipo, monto, descripcion, fecha) VALUES ({reseller_id}, 'deposito', {monto}, 'Recarga Automatica', NOW())"
            self.ejecutar_sql(sql3)
            
            return True
        return False

    def rechazar_pago(self, pago_id, motivo):
        """Marca el pago como rechazado/revisión."""
        sql = f"UPDATE historial_recargas SET nota = 'REVISAR: {motivo}' WHERE id = {pago_id}"
        # Opcional: cambiar estado a 'rechazado' si confías ciegamente en la IA
        # sql = f"UPDATE historial_recargas SET estado = 'rechazado', nota = '{motivo}' WHERE id = {pago_id}"
        return self.ejecutar_sql(sql)

    def obtener_tasa_dolar(self):
        """Obtiene la tasa actual para conversiones."""
        res = self.ejecutar_sql("SELECT valor FROM configuracion WHERE clave = 'tasa_dolar'")
        if res.get('status') == 'success' and res.get('datos'):
            return float(res['datos'][0]['valor'])
        return 0.0
