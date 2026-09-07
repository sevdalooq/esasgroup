import Swal from 'sweetalert2'

export const useSwal = () => {
  const success = (title: string, text?: string) => {
    return Swal.fire({
      icon: 'success',
      title,
      text,
      confirmButtonText: 'Tamam',
      confirmButtonColor: '#7367F0',
    })
  }

  const error = (title: string, text?: string) => {
    return Swal.fire({
      icon: 'error',
      title,
      text,
      confirmButtonText: 'Tamam',
      confirmButtonColor: '#7367F0',
    })
  }

  const warning = (title: string, text?: string) => {
    return Swal.fire({
      icon: 'warning',
      title,
      text,
      confirmButtonText: 'Tamam',
      confirmButtonColor: '#7367F0',
    })
  }

  const info = (title: string, text?: string) => {
    return Swal.fire({
      icon: 'info',
      title,
      text,
      confirmButtonText: 'Tamam',
      confirmButtonColor: '#7367F0',
    })
  }

  const confirm = (title: string, text?: string, confirmText = 'Evet', cancelText = 'Iptal') => {
    return Swal.fire({
      icon: 'warning',
      title,
      text,
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      confirmButtonColor: '#7367F0',
      cancelButtonColor: '#EA5455',
      reverseButtons: true,
    })
  }

  const confirmDelete = (itemName = 'Bu kaydi') => {
    return Swal.fire({
      icon: 'warning',
      title: 'Silmek istediginizden emin misiniz?',
      text: `${itemName} silmek uzeresiniz. Bu islem geri alinamaz.`,
      showCancelButton: true,
      confirmButtonText: 'Evet, Sil',
      cancelButtonText: 'Iptal',
      confirmButtonColor: '#EA5455',
      cancelButtonColor: '#82868B',
      reverseButtons: true,
    })
  }

  const toast = (icon: 'success' | 'error' | 'warning' | 'info', title: string) => {
    return Swal.fire({
      icon,
      title,
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    })
  }

  const prompt = (title: string, text?: string, inputPlaceholder = '') => {
    return Swal.fire({
      title,
      text,
      input: 'textarea',
      inputPlaceholder,
      showCancelButton: true,
      confirmButtonText: 'Gonder',
      cancelButtonText: 'Iptal',
      confirmButtonColor: '#7367F0',
      cancelButtonColor: '#82868B',
      reverseButtons: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Bir seyler yazmaniz gerekiyor'
        }
        return null
      },
    })
  }

  return {
    success,
    error,
    warning,
    info,
    confirm,
    confirmDelete,
    toast,
    prompt,
    Swal,
  }
}
