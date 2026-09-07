import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: `${import.meta.env.VITE_API_BASE_URL || ''}/api`,
  async onRequest({ options }) {
    const accessToken = localStorage.getItem('token')
    const isFormData = options.body instanceof FormData

    // Ensure headers object exists
    if (!options.headers)
      options.headers = new Headers()

    if (options.headers instanceof Headers) {
      options.headers.set('Accept', 'application/json')
      if (!isFormData)
        options.headers.set('Content-Type', 'application/json')
      if (accessToken)
        options.headers.set('Authorization', `Bearer ${accessToken}`)
    }
  },
})
