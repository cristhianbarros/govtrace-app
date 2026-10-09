"""It. 42b — un DuckDNS de mentira para make staging-aws-check.

Responde como la API de DuckDNS (/update?domains=…&token=…&txt=…[&clear=true]):
"OK" o "KO". Como el real, guarda UN SOLO registro TXT por dominio: el nuevo
reemplaza al anterior. Lo publica en el DNS de pebble-challtestsrv, donde lo
busca Pebble para validar. /state dice el TXT actual y lo que se pidió, sin el
token.
"""

import json
import os
import urllib.parse
import urllib.request
from http.server import BaseHTTPRequestHandler, HTTPServer

TOKEN = os.environ["FAKE_DUCKDNS_TOKEN"]
CHALLTESTSRV = os.environ.get("CHALLTESTSRV", "http://challtestsrv:8055")
state = {"txt": {}, "log": []}


def challtestsrv(path, payload):
    request = urllib.request.Request(CHALLTESTSRV + path, data=json.dumps(payload).encode(), method="POST")
    urllib.request.urlopen(request, timeout=5).read()


class DuckDns(BaseHTTPRequestHandler):
    def do_GET(self):
        url = urllib.parse.urlsplit(self.path)
        query = dict(urllib.parse.parse_qsl(url.query, keep_blank_values=True))

        if url.path == "/state":
            return self.reply(json.dumps(state))
        if url.path != "/update" or query.get("token") != TOKEN or not query.get("domains"):
            return self.reply("KO")

        domain = query["domains"]
        host = f"_acme-challenge.{domain}.duckdns.org."
        challtestsrv("/clear-txt", {"host": host})
        if query.get("clear") == "true":
            state["txt"][domain] = ""
            state["log"].append(f"clear {domain}")
        elif "txt" in query:
            challtestsrv("/set-txt", {"host": host, "value": query["txt"]})
            state["txt"][domain] = query["txt"]
            state["log"].append(f"txt {domain}")
        if "ip" in query:
            state["log"].append(f"ip {domain} {query['ip']}")
        self.reply("OK")

    def reply(self, body):
        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.end_headers()
        self.wfile.write(body.encode())

    # Sin registrar las peticiones: la URL lleva el token.
    def log_message(self, *args):
        pass


HTTPServer(("0.0.0.0", 8080), DuckDns).serve_forever()
