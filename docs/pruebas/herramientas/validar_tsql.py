import re, sys
from sqlfluff.core import Linter, FluffConfig

cfg = FluffConfig(overrides={"dialect": "tsql", "large_file_skip_byte_limit": 0})
linter = Linter(config=cfg)
total_err = 0
for ruta in sys.argv[1:]:
    texto = open(ruta, encoding="utf-8-sig").read().replace("\r\n", "\n")
    lotes, actual, inicio = [], [], 1
    for n, linea in enumerate(texto.split("\n"), 1):
        if re.match(r"^\s*GO\s*$", linea, re.I):
            lotes.append((inicio, "\n".join(actual)))
            actual, inicio = [], n + 1
        else:
            actual.append(linea)
    if "\n".join(actual).strip():
        lotes.append((inicio, "\n".join(actual)))
    errores = 0
    for inicio, sql in lotes:
        if not sql.strip():
            continue
        res = linter.parse_string(sql + "\n")
        for v in res.violations:
            errores += 1
            print(f"{ruta}:{inicio + v.line_no - 1}: {v.desc()[:160]}")
    print(f"{ruta}: {len(lotes)} lotes, {errores} errores de sintaxis")
    total_err += errores
sys.exit(1 if total_err else 0)
