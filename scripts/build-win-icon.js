const fs = require('fs');
const path = require('path');
const pngToIco = require('png-to-ico').default;

const output = path.resolve(__dirname, '..', 'logo.ico');
const inputDir = path.resolve(__dirname, '..', 'tmp', 'windows-icon-sizes');
const sizes = [16, 32, 48, 64, 128, 256];
const files = sizes.map((size) => path.join(inputDir, `${size}.png`));

if (files.some((file) => !fs.existsSync(file))) {
  throw new Error('Faltan PNG de tamaños para generar logo.ico.');
}

pngToIco(files).then((icon) => {
  fs.writeFileSync(output, icon);
  console.log(`Icono generado: ${output}`);
  console.log(`Tamanos incluidos: ${sizes.join(', ')}`);
}).catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
