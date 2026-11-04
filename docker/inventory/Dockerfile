# Spring Boot inventory service. Build context = REPO ROOT (so proto/ is visible).
# Stage 1: build with the Maven Wrapper (Maven version pinned in inventory/.mvn)
# The ~/.m2 cache mount keeps downloaded libraries between builds, even when pom.xml changes.
FROM eclipse-temurin:21-jdk AS build
WORKDIR /workspace/inventory

# mvnw ("only-script" wrapper) downloads Maven itself and needs curl + unzip
RUN apt-get update && apt-get install -y --no-install-recommends curl unzip \
    && rm -rf /var/lib/apt/lists/*

COPY inventory/.mvn .mvn
COPY inventory/mvnw inventory/pom.xml ./
RUN --mount=type=cache,target=/root/.m2 chmod +x mvnw && ./mvnw -B -q dependency:go-offline

COPY proto /workspace/proto
COPY inventory/src src
RUN --mount=type=cache,target=/root/.m2 ./mvnw -B -q package -DskipTests

# Stage 2: small runtime image, JRE only, non-root user
FROM eclipse-temurin:21-jre
WORKDIR /app
RUN groupadd --system spring && useradd --system --gid spring spring
COPY --from=build /workspace/inventory/target/*.jar app.jar
USER spring
EXPOSE 9090 8080
ENTRYPOINT ["java", "-jar", "/app/app.jar"]
