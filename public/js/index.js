$('.add_favorite').click(function (event) {
  var favorite = event.target
  var post_id = favorite.id
  // Almacena la URL actual
  var currentUrl = window.location.href
  var url = $(this).data('url')

  Swal.fire({
    title: 'Agregar a favoritos?',
    showCancelButton: true,
    confirmButtonText: 'Agregar',
    cancelButtonText: 'Cancelar',
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        type: 'POST',
        url: url,
        data: { post_id: post_id },
        async: true,
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            Swal.fire({
              title: 'Agregado',
              text: response.message,
              icon: 'success',
              timer: 2500,
              showConfirmButton: false,
            }).then(() => {
              window.location.href = currentUrl
            })
          } else {
            Swal.fire({
              title: 'Error',
              text: response.message,
              icon: 'error',
            })
          }
        },
      })
    } else if (result.dismiss === Swal.DismissReason.cancel) {
      Swal.fire({
        title: 'Cancelado',
        text: 'No se ha agregado a favoritos',
        icon: 'error',
        timer: 2500,
        showConfirmButton: false,
      })
    }
  })
})

$('.remove_favorite').click(function (event) {
  var favorite = event.target
  var post_id = favorite.id
  // Almacena la URL actual
  var currentUrl = window.location.href
  var url = $(this).data('url')

  Swal.fire({
    title: 'Eliminar de favoritos?',
    showCancelButton: true,
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        type: 'POST',
        url: url,
        data: { post_id: post_id },
        async: true,
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            Swal.fire({
              title: 'Eliminado',
              text: response.message,
              icon: 'success',
              timer: 2500,
              showConfirmButton: false,
            }).then(() => {
              window.location.href = currentUrl
            })
          } else {
            Swal.fire({
              title: 'Error',
              text: response.message,
              icon: 'error',
            })
          }
        },
      })
    } else if (result.dismiss === Swal.DismissReason.cancel) {
      Swal.fire({
        title: 'Cancelado',
        text: 'No se ha eliminado de favoritos',
        icon: 'error',
        timer: 2500,
        showConfirmButton: false,
      })
    }
  })
})

$('#delete').click(function () {
  Swal.fire({
    title: '¿Deseas eliminar el Post?',
    showCancelButton: true,
    cancelButtonText: 'Cancelar',
    confirmButtonText: 'Eliminar'
  }).then((result) => {
    if (result.isConfirmed) {
      var deleteUrl = $(this).data('url')

      $.ajax({
        url: deleteUrl,
        type: 'POST',
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            Swal.fire({
              title: 'Eliminado',
              text: response.message,
              icon: 'success',
              timer: 2500,
              showConfirmButton: false
            }).then(() => {
              window.location.href = '/'
            })
          } else {
            Swal.fire({
              title: 'Error',
              text: response.message,
              icon: 'error'
            })
          }
        },
        error: function () {
          Swal.fire({
            title: 'Error',
            text: 'Hubo un problema al intentar eliminar el post',
            icon: 'error',
            timer: 2500,
            showConfirmButton: false,
          })
        }
      })
    }
  })
})