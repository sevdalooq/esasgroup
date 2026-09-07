<script setup lang="ts">
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  email: '',
  password: '',
  remember: false,
})

const isPasswordVisible = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const authThemeImg = useGenerateImageVariant(
  authV2LoginIllustrationLight,
  authV2LoginIllustrationDark,
  authV2LoginIllustrationBorderedLight,
  authV2LoginIllustrationBorderedDark,
  true)

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

const login = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const res = await $api('/login', {
      method: 'POST',
      body: {
        email: form.value.email,
        password: form.value.password,
      },
    })

    // Store auth data in pinia store
    authStore.setAuthData({
      user: res.user,
      token: res.token,
      permissions: res.permissions,
      is_admin: res.is_admin,
    })

    // Also store in cookies for SSR compatibility
    useCookie('userData').value = res.user
    useCookie('accessToken').value = res.token

    // Redirect to dashboard
    router.push('/')
  }
  catch (err: any) {
    console.error('Login error:', err)
    if (err.data?.message) {
      errorMessage.value = err.data.message
    }
    else if (err.data?.errors) {
      const errors = err.data.errors
      errorMessage.value = Object.values(errors).flat().join(', ')
    }
    else {
      errorMessage.value = 'Giris yapilirken bir hata olustu'
    }
  }
  finally {
    isLoading.value = false
  }
}
</script>

<template>
  <a href="javascript:void(0)">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </a>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 6.25rem;"
        >
          <VImg
            max-width="613"
            :src="authThemeImg"
            class="auth-illustration mt-16 mb-2"
            eager
          />
        </div>

        <img
          class="auth-footer-mask flip-in-rtl"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center px-6"
    >
      <VCard
        flat
        class="mt-12 mt-sm-0 pa-4 w-100"
        style="max-width: 450px; min-width: 320px;"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            {{ themeConfig.app.title }}
          </h4>
          <p class="mb-0">
            Devam etmek icin giris yapin
          </p>
        </VCardText>
        <VCardText>
          <VAlert
            v-if="errorMessage"
            type="error"
            class="mb-4"
            closable
            @click:close="errorMessage = ''"
          >
            {{ errorMessage }}
          </VAlert>

          <VForm @submit.prevent="login">
            <VRow>
              <!-- E-posta -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  autofocus
                  label="E-posta"
                  type="email"
                  placeholder="ornek@sirket.com"
                />
              </VCol>

              <!-- Şifre -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.password"
                  label="Sifre"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="current-password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center flex-wrap justify-space-between my-6">
                  <VCheckbox
                    v-model="form.remember"
                    label="Oturumumu acik tut"
                  />
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  Giris Yap
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";

.auth-wrapper {
  display: flex;
  flex: 1;
  min-block-size: 100vh;

  .auth-card-v2 {
    min-inline-size: 420px;

    @media (max-width: 960px) {
      min-inline-size: 100%;
    }
  }
}
</style>
