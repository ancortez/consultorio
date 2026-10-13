/**
 * Puerto de la regla que decide con que denticion abre el odontograma.
 * Es lo unico con logica en el cambio; el resto es dibujo.
 */
function conQueDenticionAbre(elegidos, edadCruda) {
  if (elegidos.length > 0) {
    return elegidos.some((n) => n >= 51) ? 'temporal' : 'permanente';
  }
  const edad = Number(edadCruda);
  return (Number.isFinite(edad) && edad > 0 ? edad < 12 : false)
    ? 'temporal' : 'permanente';
}

const casos = [
  // Sin nada elegido: manda la edad.
  [[], 3,    'temporal',   'bebe de 3 anios'],
  [[], 5,    'temporal',   'nino de 5'],
  [[], 6,    'temporal',   'nino de 6, denticion mixta'],
  [[], 9,    'temporal',   'nino de 9, mixta'],
  [[], 11,   'temporal',   'nino de 11, ultimo anio de mixta'],
  [[], 12,   'permanente', 'nino de 12, ya permanente'],
  [[], 15,   'permanente', 'adolescente'],
  [[], 40,   'permanente', 'adulto'],

  // Lo ya elegido manda sobre la edad: alguien ya decidio.
  [[51, 52], 40, 'temporal',   'adulto con dientes temporales ya elegidos'],
  [[11, 12], 4,  'permanente', 'nino con permanentes ya elegidos'],
  [[85],     30, 'temporal',   'un solo temporal elegido'],
  [[48],     3,  'permanente', 'un solo permanente elegido'],

  // Edad ausente o rara: no se inventa nada, abre en permanente.
  [[], null,      'permanente', 'sin edad'],
  [[], '',        'permanente', 'edad vacia'],
  [[], undefined, 'permanente', 'edad indefinida'],
  [[], 0,         'permanente', 'edad cero (dato malo)'],
  [[], 'abc',     'permanente', 'edad no numerica'],
  [[], -5,        'permanente', 'edad negativa'],
];

let fallos = 0;
for (const [elegidos, edad, espera, desc] of casos) {
  const sale = conQueDenticionAbre(elegidos, edad);
  const bien = sale === espera;
  console.log(`  ${bien ? 'ok  ' : 'MAL '} ${desc}: abre en ${sale}`);
  if (!bien) { console.log(`        esperaba ${espera}`); fallos++; }
}
console.log();
console.log(fallos ? `*** ${fallos} PROBLEMA(S) ***` : '*** TODO EN ORDEN ***');
process.exit(fallos ? 1 : 0);
