/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.vue",
    "./resources/**/*.js",
    "./resources/js/Pages/**/*.vue",
    "./resources/js/Layouts/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        // Colores corporativos
        guindo: {
          DEFAULT: '#61131a',
          50: '#fdf2f2',
          100: '#fce8e8',
          200: '#f9d0d0',
          300: '#f4a9a9',
          400: '#ec7878',
          500: '#e04e4e',
          600: '#c63030',
          700: '#a62424',
          800: '#882020',
          900: '#61131a',
          950: '#3a0a0f',
        },
        amarillo: {
          DEFAULT: '#eab308',
          50: '#fefce8',
          100: '#fef9c3',
          200: '#fef08a',
          300: '#fde047',
          400: '#facc15',
          500: '#eab308',
          600: '#ca8a04',
          700: '#a16207',
          800: '#854d0e',
          900: '#713f12',
          950: '#422006',
        },
      },
    },
  },
  plugins: [],
}