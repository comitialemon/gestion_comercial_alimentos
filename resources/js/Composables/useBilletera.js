import { ref, computed } from 'vue'
import axios from 'axios'

// ============================================================
// ESTADO GLOBAL COMPARTIDO (singleton)
// Se comparte entre todos los componentes que usen el composable
// ============================================================
const saldoPolling = ref(null)
const pollingActivo = ref(false)
const pollingFinEn = ref(null)

let intervalo = null

// ============================================================
// COMPOSABLE
// ============================================================
export function useBilletera() {

    /**
     * Saldo más reciente disponible
     * Prioriza el polling (más fresco) sobre Inertia
     */
    const saldoMasReciente = computed(() => {
        if (saldoPolling.value?.tiene_billetera !== undefined) {
            return saldoPolling.value
        }
        return null
    })

    /**
     * Activa polling temporal (por N minutos)
     * Útil cuando el operador espera una recarga
     */
    const activarPollingTemporal = (minutos = 5) => {
        // Si ya está activo, solo extender el tiempo
        const nuevoFinEn = Date.now() + minutos * 60 * 1000
        pollingFinEn.value = nuevoFinEn

        if (pollingActivo.value) {
            // Ya está corriendo, solo extendimos
            return
        }

        pollingActivo.value = true

        // Primera consulta inmediata
        consultarSaldo()

        // Intervalo cada 10 segundos
        intervalo = setInterval(() => {
            // ¿Ya pasó el tiempo?
            if (Date.now() > pollingFinEn.value) {
                desactivarPolling()
                return
            }

            consultarSaldo()
        }, 10000)
    }

    /**
     * Desactiva polling manualmente
     */
    const desactivarPolling = () => {
        if (intervalo) {
            clearInterval(intervalo)
            intervalo = null
        }
        pollingActivo.value = false
        pollingFinEn.value = null
    }

    /**
     * Consulta el saldo al backend
     */
    const consultarSaldo = async () => {
        try {
            const res = await axios.get(
                '/operacion/pedidos/clientes-mayoristas/mi-billetera/api/mi-saldo',
                { timeout: 5000 }
            )
            saldoPolling.value = res.data
        } catch (e) {
            // Silencioso: no queremos llenar la consola de errores
            // console.warn('Error consultando saldo:', e.message)
        }
    }

    /**
     * Limpia el estado (útil al logout)
     */
    const limpiar = () => {
        desactivarPolling()
        saldoPolling.value = null
    }

    return {
        saldoPolling,
        pollingActivo,
        saldoMasReciente,
        activarPollingTemporal,
        desactivarPolling,
        consultarSaldo,
        limpiar,
    }
}