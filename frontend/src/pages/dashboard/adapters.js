function toNumber(value, fallback = 0) {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : fallback
}

function normalizeKey(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, '_')
}

function statusToBadge(status) {
    const key = normalizeKey(status)
    if (['atrasado', 'critico', 'parado', 'travado', 'impedido', 'bloqueado', 'bloqueada'].includes(key)) {
        return 'travado'
    }
    if (['atencao', 'em_espera', 'proximo_prazo', 'risco'].includes(key)) {
        return 'atencao'
    }
    if (['concluido', 'concluida', 'finalizado', 'finalizada'].includes(key)) {
        return 'concluido'
    }
    if (['suspenso', 'suspensa', 'cancelado', 'cancelada', 'arquivado', 'arquivada'].includes(key)) {
        return 'suspenso'
    }

    return 'em_dia'
}

function daysDiffFromToday(dateValue) {
    if (!dateValue) return null

    const raw = new Date(dateValue)
    if (Number.isNaN(raw.getTime())) return null

    const today = new Date()
    const a = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate())
    const b = Date.UTC(raw.getFullYear(), raw.getMonth(), raw.getDate())
    const diff = Math.floor((b - a) / 86400000)
    return diff
}

function normalizeAlertList(alertPayload) {
    const source = Array.isArray(alertPayload)
        ? alertPayload
        : (Array.isArray(alertPayload?.data) ? alertPayload.data : [])

    return source.map((item, index) => {
        const prioridade = normalizeKey(item?.prioridade || 'media')
        const prioridadeMap = {
            critica: 'critica',
            critico: 'critica',
            alta: 'alta',
            media: 'media',
            medio: 'media',
            baixa: 'baixa',
        }

        return {
            id: toNumber(item?.id, index + 1),
            titulo: String(item?.titulo || item?.mensagem || 'Alerta'),
            descricao: item?.mensagem ? String(item.mensagem) : null,
            prioridade: prioridadeMap[prioridade] || 'media',
            data_criacao: item?.created_at || item?.data_criacao || new Date().toISOString(),
            lido: toNumber(item?.lido, 0) === 1,
            projeto: item?.projeto_nome || null,
        }
    })
}

function summarizeProjectStatusRows(rows) {
    const safeRows = Array.isArray(rows) ? rows : []
    let total = 0
    let atencao = 0
    let travados = 0
    let concluidos = 0

    safeRows.forEach((row) => {
        const count = toNumber(row?.total, 0)
        const badge = statusToBadge(row?.estado)
        total += count

        if (badge === 'atencao') atencao += count
        if (badge === 'travado') travados += count
        if (badge === 'concluido') concluidos += count
    })

    const emDia = Math.max(total - atencao - travados, 0)
    return { total, emDia, atencao, travados, concluidos }
}

export function adaptPrefeitoDashboard(rawData, alertPayload = null) {
    const data = rawData || {}
    const ppaExecucao = data.ppa_execucao || {}
    const projetosStatusSummary = summarizeProjectStatusRows(data.projetos_status)
    const orcamento = data.orcamento || {}
    const valorPrevisto = toNumber(orcamento.previsto, 0)
    const valorPago = toNumber(orcamento.pago, 0)
    const emendas = data.emendas || {}
    const alerts = normalizeAlertList(alertPayload || data.alertas)

    const obras = (Array.isArray(data.obras_atrasadas) ? data.obras_atrasadas : []).map((item, index) => {
        const diasRestantes = daysDiffFromToday(item?.data_prevista_fim)
        const atrasoDias = toNumber(item?.dias_atraso, 0) > 0
            ? toNumber(item?.dias_atraso, 0)
            : Math.max(toNumber(diasRestantes, 0) * -1, 0)

        return {
            id: toNumber(item?.id, index + 1),
            nome: String(item?.nome || 'Projeto'),
            prazo_dias: diasRestantes !== null ? Math.max(diasRestantes, 0) : 0,
            status: statusToBadge(item?.estado),
            secretaria: item?.secretaria || item?.unidade_nome || 'Nao informada',
            atraso_dias: atrasoDias,
        }
    })

    const percentualExecutado = toNumber(
        orcamento.percentual_executado,
        valorPrevisto > 0 ? (valorPago / valorPrevisto) * 100 : 0
    )
    const valorEmRisco = Math.max(valorPrevisto - valorPago, 0)
    const totalProgramas = toNumber(ppaExecucao.total_programas, 0)
    const concluidos = toNumber(ppaExecucao.concluidos, 0)

    return {
        perfil: data.perfil || 'prefeito',
        source: data.source || 'unknown',
        indicadores: {
            projetos_em_dia: projetosStatusSummary.emDia,
            projetos_atencao: projetosStatusSummary.atencao,
            projetos_travados: projetosStatusSummary.travados,
            valor_em_risco: valorEmRisco,
        },
        ppa: {
            percentual: toNumber(ppaExecucao.percentual_medio, 0),
            programas_ativos: Math.max(totalProgramas - concluidos, 0),
            programas_total: totalProgramas,
        },
        alertas: alerts,
        obras_destaque: obras,
        emendas: {
            recebidas: valorPrevisto,
            percentual_executado: percentualExecutado,
            em_risco: toNumber(emendas.em_risco, valorEmRisco),
            nao_executadas: Math.max(100 - percentualExecutado, 0),
        },
        saude_resumo: data.saude_resumo || null,
    }
}

export function adaptSecretarioDashboard(rawData, alertPayload = null) {
    const data = rawData || {}
    const projetosResumo = summarizeProjectStatusRows(data.projetos_resumo)
    const programas = (Array.isArray(data.programas) ? data.programas : []).map((item) => ({
        nome: String(item?.nome || 'Programa'),
        status: statusToBadge(item?.estado),
        projetos: toNumber(item?.total_projetos, 0),
        travados: 0,
        percentual_execucao: toNumber(item?.percent_execucao, 0),
    }))

    const alertas = normalizeAlertList(alertPayload)
    const stats = data.alertas && !Array.isArray(data.alertas) ? data.alertas : {}

    return {
        perfil: data.perfil || 'secretario',
        source: data.source || 'unknown',
        secretaria: {
            nome: data.unidade_nome || 'Secretaria',
            projetos: projetosResumo.total,
            equipe: toNumber(data.coordenadores?.length, 0),
        },
        indicadores: {
            em_dia: projetosResumo.emDia,
            atencao: projetosResumo.atencao,
            travados: projetosResumo.travados,
            concluidos: projetosResumo.concluidos,
        },
        programas,
        coordenadores: Array.isArray(data.coordenadores) ? data.coordenadores : [],
        alertas,
        alertas_estatisticas: {
            total: toNumber(stats.total, alertas.length),
            nao_lidos: toNumber(stats.nao_lidos, 0),
            criticos: toNumber(stats.criticos, 0),
        },
        saude_resumo: data.saude_resumo || null,
    }
}

export function adaptCoordenadorDashboard(rawData) {
    const data = rawData || {}
    const resumo = data.resumo || {}
    const projetos = Array.isArray(data.projetos) ? data.projetos : []

    const groupedByPrograma = new Map()
    projetos.forEach((item) => {
        const name = item?.programa_nome || 'Sem programa'
        const current = groupedByPrograma.get(name) || {
            nome: name,
            projetos: 0,
            orcamento: 0,
            executado_sum: 0,
            travados: 0,
        }

        current.projetos += 1
        current.executado_sum += toNumber(item?.percent_execucao, 0)
        if (statusToBadge(item?.estado) === 'travado') {
            current.travados += 1
        }

        groupedByPrograma.set(name, current)
    })

    const programas = Array.from(groupedByPrograma.values()).map((item) => ({
        nome: item.nome,
        projetos: item.projetos,
        orcamento: item.orcamento,
        executado: item.projetos > 0 ? Math.round(item.executado_sum / item.projetos) : 0,
        travados: item.travados,
    }))

    const equipe = (Array.isArray(data.equipe) ? data.equipe : []).map((item) => {
        const tarefasAtivas = toNumber(item?.tarefas_ativas, 0)
        const performance = tarefasAtivas <= 3 ? 'Excelente' : (tarefasAtivas <= 6 ? 'Boa' : 'Sobrecarregado')

        return {
            nome: String(item?.nome || 'Tecnico'),
            tarefas_ativas: tarefasAtivas,
            concluidas: toNumber(item?.concluidas, 0),
            performance,
        }
    })

    return {
        perfil: data.perfil || 'coordenador',
        source: data.source || 'unknown',
        usuario: data.usuario || {
            nome: 'Coordenador',
            secretaria: 'Nao informada',
            programas: programas.length,
            projetos: toNumber(resumo.total_projetos, projetos.length),
            tecnicos: equipe.length,
        },
        indicadores: {
            em_dia: Math.max(toNumber(resumo.em_andamento, 0) - toNumber(resumo.atrasados, 0), 0),
            atencao: 0,
            travados: toNumber(resumo.atrasados, 0),
            concluidos: toNumber(resumo.concluidos, 0),
        },
        programas,
        proximas_etapas: Array.isArray(data.proximas_etapas) ? data.proximas_etapas : [],
        equipe,
    }
}

function mapTaskPriority(urgencia) {
    const key = normalizeKey(urgencia)
    if (key === 'atrasada') return 'urgente'
    if (key === 'urgente') return 'importante'
    return 'normal'
}

export function adaptTecnicoDashboard(rawData) {
    const data = rawData || {}
    const resumo = data.resumo || {}
    const tarefas = Array.isArray(data.tarefas_prioritarias) ? data.tarefas_prioritarias : []
    const projetos = Array.isArray(data.projetos) ? data.projetos : []
    const produtividade = Array.isArray(data.produtividade_30d) ? data.produtividade_30d : []

    const semana = produtividade.slice(0, 7).reduce((acc, item) => acc + toNumber(item?.tarefas_concluidas, 0), 0)
    const mes = produtividade.reduce((acc, item) => acc + toNumber(item?.tarefas_concluidas, 0), 0)
    const mediaDia = produtividade.length > 0 ? Number((mes / produtividade.length).toFixed(1)) : 0

    const mappedTarefas = tarefas.map((item) => ({
        id: toNumber(item?.id, 0),
        titulo: String(item?.nome || item?.tarefa || 'Tarefa'),
        projeto: item?.projeto || 'Projeto',
        prioridade: mapTaskPriority(item?.urgencia),
        vence_hoje: toNumber(item?.dias_restantes, 999) === 0,
        vence_dias: toNumber(item?.dias_restantes, 0) > 0 ? toNumber(item?.dias_restantes, 0) : null,
    }))

    const mappedProjetos = projetos.map((item) => ({
        nome: String(item?.nome || item?.projeto || 'Projeto'),
        tarefas_ativas: toNumber(item?.minhas_tarefas, 0),
        status: toNumber(item?.percent_execucao, 0) >= 80 ? 'em_dia' : 'atencao',
    }))

    return {
        perfil: data.perfil || 'tecnico',
        source: data.source || 'unknown',
        usuario: data.usuario || {
            nome: 'Tecnico',
            coordenacao: 'Nao informada',
            supervisor: 'Nao informado',
        },
        indicadores: {
            a_fazer: Math.max(toNumber(resumo.pendentes, 0) - toNumber(resumo.atrasadas, 0), 0),
            em_andamento: toNumber(resumo.pendentes, 0),
            em_revisao: 0,
            concluidas: toNumber(resumo.concluidas, 0),
        },
        tarefas: mappedTarefas,
        projetos: mappedProjetos,
        producao: {
            semana,
            mes,
            media_dia: mediaDia,
            taxa_prazo: toNumber(resumo.total, 0) > 0
                ? Math.round(((toNumber(resumo.total, 0) - toNumber(resumo.atrasadas, 0)) / toNumber(resumo.total, 0)) * 100)
                : 100,
        },
    }
}

export function adaptControladorDashboard(rawData) {
    const data = rawData || {}
    const conformidade = data.alertas_conformidade || {}
    const secretarias = Array.isArray(data.panorama_secretarias) ? data.panorama_secretarias : []
    const irregularidades = Array.isArray(data.irregularidades) ? data.irregularidades : []

    const criticos = toNumber(conformidade.sem_prestacao_contas, 0) + toNumber(conformidade.execucao_acima_cronograma, 0)
    const atencao = toNumber(conformidade.diferenca_empenho_execucao, 0)
    const totalProjetos = secretarias.reduce((acc, item) => acc + toNumber(item?.total_projetos, 0), 0)
    const conformidadePercentual = totalProjetos > 0
        ? Math.max(0, Math.round(((totalProjetos - criticos - atencao) / totalProjetos) * 100))
        : 100

    const mappedSecretarias = secretarias.map((item) => ({
        nome: item?.unidade_nome || 'Secretaria',
        projetos: toNumber(item?.total_projetos, 0),
        alertas: toNumber(item?.alertas, 0),
        execucao: Math.round(toNumber(item?.execucao_media, 0)),
        transparencia: 100 - Math.min(100, toNumber(item?.alertas, 0) * 10),
    }))

    const mappedAlertas = irregularidades.map((item, index) => ({
        id: toNumber(item?.id, index + 1),
        titulo: item?.irregularidade || item?.nome || 'Irregularidade identificada',
        prioridade: 'critica',
        data_criacao: new Date().toISOString(),
        projeto: item?.nome || null,
    }))

    return {
        perfil: data.perfil || 'controlador',
        source: data.source || 'unknown',
        usuario: data.usuario || { nome: 'Controlador' },
        indicadores: {
            alertas_criticos: criticos,
            alertas_atencao: atencao,
            projetos_auditados: totalProjetos,
            conformidade: conformidadePercentual,
        },
        alertas: mappedAlertas,
        secretarias: mappedSecretarias,
        relatorios: Array.isArray(data.relatorios) ? data.relatorios : [],
    }
}
