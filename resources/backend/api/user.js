import request from '@/utils/request'
//请求后台
export function login(data) {
  return request({
    url: '/user/login',
    method: 'post',
    data(){
      console.log(data)
    }
  })
}

export function getInfo(token) {
  return request({
    url: '/user/info',
    method: 'get',
    params: { token }
  })
}

export function logout() {
  return request({
    url: '/user/logout',
    method: 'post'
  })
}
