import { defineStore } from 'pinia'

export const usePwaStore = defineStore('pwa', {
  state: () => ({
    installPrompt: null,
    isInstalled: window.matchMedia('(display-mode: standalone)').matches,
  }),
  actions: {
    setInstallPrompt(event) {
      this.installPrompt = event
    },
    async triggerInstall() {
      if (!this.installPrompt) return
      this.installPrompt.prompt()
      const { outcome } = await this.installPrompt.userChoice
      if (outcome === 'accepted') {
        this.isInstalled = true
      }
      this.installPrompt = null
    },
  },
})
