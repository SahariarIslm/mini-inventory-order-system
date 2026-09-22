import type { Paginated } from '../api/types'
import { useResource, type ResourceState } from './useResource'

export type PaginatedState<T> = ResourceState<Paginated<T>>

/** One page of a paginated endpoint. `load` must be stable. */
export function usePaginated<T>(load: (page: number) => Promise<Paginated<T>>, page: number): PaginatedState<T> {
  return useResource(load, page)
}
