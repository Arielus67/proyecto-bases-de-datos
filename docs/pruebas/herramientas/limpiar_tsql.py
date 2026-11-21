import re, sys

def sin_comentarios(texto):
    salida, i, n, prof = [], 0, len(texto), 0
    while i < n:
        c = texto[i]
        if prof:
            if texto.startswith('/*', i):
                prof += 1; i += 2
            elif texto.startswith('*/', i):
                prof -= 1; i += 2
            else:
                if c == '\n':
                    salida.append('\n')
                i += 1
            continue
        if texto.startswith('/*', i):
            prof = 1; i += 2; continue
        if texto.startswith('--', i):
            while i < n and texto[i] != '\n':
                i += 1
            continue
        if c == "'":
            j = i + 1
            while j < n:
                if texto[j] == "'" and j + 1 < n and texto[j + 1] == "'":
                    j += 2; continue
                if texto[j] == "'":
                    break
                j += 1
            salida.append(texto[i:j + 1]); i = j + 1; continue
        if c == '[':
            j = texto.index(']', i)
            salida.append(texto[i:j + 1]); i = j + 1; continue
        salida.append(c); i += 1
    return ''.join(salida)

def termina_en_puntoycoma_fuera_de_cadena(linea):
    dentro = False
    for ch in linea:
        if ch == "'":
            dentro = not dentro
    return not dentro and linea.rstrip().endswith(';')

CONSERVAR = re.compile(r'^\s*(BEGIN\s+TRAN|COMMIT|ROLLBACK|SAVE\s+TRAN|RETURN\b|IF\b.*\b(BEGIN\s+TRAN\w*|COMMIT\s+TRAN\w*|ROLLBACK\s+TRAN\w*)\s*;\s*$)', re.I)
SIGUIENTE_EXIGE = re.compile(r'^\s*(THROW\b|WITH\b|;)', re.I)

def quitar_puntoycoma(texto):
    lineas = texto.split('\n')
    codigo = [i for i, l in enumerate(lineas) if l.strip()]
    siguiente = {codigo[k]: codigo[k + 1] for k in range(len(codigo) - 1)}
    for i, l in enumerate(lineas):
        if not termina_en_puntoycoma_fuera_de_cadena(l):
            continue
        sig = lineas[siguiente[i]] if i in siguiente else ''
        if CONSERVAR.match(l) or SIGUIENTE_EXIGE.match(sig):
            continue
        lineas[i] = l.rstrip()[:-1].rstrip()
    return '\n'.join(lineas)

def compactar(texto):
    lineas = [l.rstrip() for l in texto.split('\n')]
    salida = []
    for l in lineas:
        if not l and (not salida or not salida[-1]):
            continue
        salida.append(l)
    texto = '\n'.join(salida)
    texto = re.sub(r'\n+(\s*GO\s*\n)', r'\n\1', texto)
    texto = re.sub(r'(\n\s*GO\s*\n)\n+', r'\1\n', texto)
    texto = re.sub(r'(AS\n(BEGIN\n)?)\n+', r'\1', texto)
    texto = re.sub(r'\n\n+(\s*END\b)', r'\n\1', texto)
    texto = re.sub(r'(\n\s*BEGIN( TRY| CATCH)?\n)\n+', r'\1', texto)
    return texto.strip('\n') + '\n'

if __name__ == '__main__':
    origen, destino = sys.argv[1], sys.argv[2]
    texto = open(origen, encoding='utf-8-sig').read().replace('\r\n', '\n')
    texto = compactar(quitar_puntoycoma(compactar(sin_comentarios(texto))))
    open(destino, 'w', encoding='utf-8').write(texto)
