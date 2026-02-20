import os

def load_env():
    env_path = os.path.join(os.path.dirname(__file__), '..', '.env')
    if os.path.exists(env_path):
        with open(env_path, 'r') as f:
            for line in f:
                if line.strip() and not line.startswith('#'):
                    key, value = line.strip().split('=', 1)
                    os.environ[key] = value

load_env()

TOKEN_TELEGRAM = os.getenv("TELEGRAM_TOKEN", "tu_token_aqui")
ADMIN_ID = int(os.getenv("TELEGRAM_ADMIN_ID", "5904743482"))
CHANNEL_ID = int(os.getenv("TELEGRAM_CHANNEL_ID", "-1003279968737"))

GENAI_API_KEY = os.getenv("GEMINI_API_KEY", "tu_key_aqui")
URL_GEMINI_VISION = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-preview:generateContent?key={GENAI_API_KEY}"
URL_GEMINI_TEXT = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-preview:generateContent?key={GENAI_API_KEY}"

URL_API_DB = "https://levelplaymax.com/admin/api_master.php"
TOKEN_SECRETO_DB = os.getenv("SECRET_KEY_DB", "Ghost_2025_acceso_total")

BASE_URL_IMAGENES = "https://levelplaymax.com/"

INTERVALO_AUDITORIA = 60

