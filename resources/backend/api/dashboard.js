import request from '@/utils/request'

export function fetchDashborad(query) {
  return request({
    url: '/index/indexmain',
    method: 'get',
    // params: query
  })
}

