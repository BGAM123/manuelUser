package com.jiraws.library.configuration;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.boot.persistence.autoconfigure.EntityScan;
import org.springframework.data.jpa.repository.config.EnableJpaRepositories;

@EntityScan("com.jiraws.library.book.Entities")
@EnableJpaRepositories(basePackages = "com.jiraws.library.book.Repositories")
@SpringBootApplication(scanBasePackages = {
        "com.jiraws.library.book", "com.example.springboot"
})
public class Application {

    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }

}
