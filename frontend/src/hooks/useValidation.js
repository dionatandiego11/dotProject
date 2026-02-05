/**
 * Hook de Validacao
 *
 * Validacoes comuns para formularios
 */

import { useState, useCallback } from 'react'

export function useValidation() {
    const [errors, setErrors] = useState({})

    const validateRequired = useCallback((value, fieldName) => {
        if (!value || (typeof value === 'string' && !value.trim())) {
            return `${fieldName} e obrigatorio`
        }
        return null
    }, [])

    const validateMinLength = useCallback((value, min, fieldName) => {
        if (value && value.length < min) {
            return `${fieldName} deve ter pelo menos ${min} caracteres`
        }
        return null
    }, [])

    const validateMaxLength = useCallback((value, max, fieldName) => {
        if (value && value.length > max) {
            return `${fieldName} deve ter no maximo ${max} caracteres`
        }
        return null
    }, [])

    const validateEmail = useCallback((value, fieldName = 'Email') => {
        if (!value) return null
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!pattern.test(value)) {
            return `${fieldName} invalido`
        }
        return null
    }, [])

    const validateDateRange = useCallback((startDate, endDate, startName = 'Data inicial', endName = 'Data final') => {
        if (startDate && endDate) {
            const start = new Date(startDate)
            const end = new Date(endDate)
            if (end < start) {
                return `${endName} deve ser posterior a ${startName.toLowerCase()}`
            }
        }
        return null
    }, [])

    const validateFutureDate = useCallback((date, fieldName) => {
        if (date) {
            const inputDate = new Date(date)
            const today = new Date()
            today.setHours(0, 0, 0, 0)

            if (inputDate < today) {
                return `${fieldName} nao pode ser no passado`
            }
        }
        return null
    }, [])

    const clearErrors = useCallback(() => {
        setErrors({})
    }, [])

    const clearFieldError = useCallback((field) => {
        setErrors(prev => ({ ...prev, [field]: null }))
    }, [])

    const setFieldError = useCallback((field, error) => {
        setErrors(prev => ({ ...prev, [field]: error }))
    }, [])

    const validateFields = useCallback((validations) => {
        const newErrors = {}
        let isValid = true

        for (const [field, validation] of Object.entries(validations)) {
            const error = validation()
            if (error) {
                newErrors[field] = error
                isValid = false
            }
        }

        setErrors(newErrors)
        return isValid
    }, [])

    return {
        errors,
        setErrors,
        validateRequired,
        validateMinLength,
        validateMaxLength,
        validateEmail,
        validateDateRange,
        validateFutureDate,
        clearErrors,
        clearFieldError,
        setFieldError,
        validateFields,
        hasErrors: Object.keys(errors).some(key => errors[key])
    }
}
