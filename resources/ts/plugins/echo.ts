import type { App } from 'vue'
import { destroyEcho, getEcho } from '@/composables/useEcho'
import { store } from '@/plugins/2.pinia'
import { useAuthStore } from '@/stores/auth'

/**
 * Websocket (Laravel Reverb) bağlantısını oturumla eşler:
 * çıkışta bağlantı kapatılır, oturum zaten açıksa Echo hazırlanır (tembel).
 */
export default function (_app: App) {
  const auth = useAuthStore(store)

  auth.$onAction(({ name, after }) => {
    if (name === 'logout')
      after(() => destroyEcho())
    if (name === 'setAuthData')
      after(() => getEcho())
  })
}
